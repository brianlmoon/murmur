<?php

declare(strict_types=1);

namespace Murmur\Controller;

use Murmur\Repository\SettingMapper;
use Murmur\Service\OAuthService;
use Murmur\Service\SessionService;
use Twig\Environment;

/**
 * Controller for OAuth authentication routes.
 *
 * Handles OAuth provider authorization, callbacks, and account linking.
 */
class OAuthController extends BaseController {

    /**
     * OAuth service.
     */
    protected OAuthService $oauth_service;

    /**
     * Creates a new OAuthController instance.
     *
     * @param Environment    $twig           Twig environment.
     * @param SessionService $session        Session service.
     * @param SettingMapper  $setting_mapper Setting mapper.
     * @param OAuthService   $oauth_service  OAuth service.
     */
    public function __construct(
        Environment $twig,
        SessionService $session,
        SettingMapper $setting_mapper,
        OAuthService $oauth_service
    ) {
        parent::__construct($twig, $session, $setting_mapper);
        $this->oauth_service = $oauth_service;
    }

    /**
     * Redirects to OAuth provider for authorization.
     *
     * GET /oauth/{provider}
     *
     * @param string $provider The provider name (google, facebook, apple).
     *
     * @return void
     */
    public function authorize(string $provider): void {
        $this->requireGuest();

        if (!$this->oauth_service->isProviderEnabled($provider)) {
            $this->session->addFlash(
                'error',
                "Sign-in with {$provider} is currently disabled."
            );
            $this->redirect('/login');
        }

        $state = bin2hex(random_bytes(32));
        $_SESSION['oauth_state'] = $state;
        $_SESSION['oauth_provider'] = $provider;

        $auth_url = $this->oauth_service->getAuthorizationUrl(
            $provider,
            $state
        );

        if ($auth_url === null) {
            $this->session->addFlash(
                'error',
                "OAuth provider {$provider} is not configured."
            );
            $this->redirect('/login');
        }

        header('Location: ' . $auth_url);
        exit;
    }

    /**
     * Handles OAuth callback from provider.
     *
     * GET /oauth/{provider}/callback
     *
     * @param string $provider The provider name.
     *
     * @return string The rendered HTML or redirect.
     */
    public function callback(string $provider): string {
        $result = '';

        $this->requireGuest();

        if (!$this->oauth_service->isProviderEnabled($provider)) {
            $this->session->addFlash(
                'error',
                "Sign-in with {$provider} is currently disabled."
            );
            $this->redirect('/login');
        }

        $code = $this->getQuery('code', '');
        $state = $this->getQuery('state', '');
        $stored_state = $_SESSION['oauth_state'] ?? '';
        $stored_provider = $_SESSION['oauth_provider'] ?? '';

        unset($_SESSION['oauth_state'], $_SESSION['oauth_provider']);

        if ($state === '' || $state !== $stored_state) {
            $this->session->addFlash('error', 'Invalid OAuth state. Please try again.');
            $this->redirect('/login');
        }

        if ($provider !== $stored_provider) {
            $this->session->addFlash('error', 'OAuth provider mismatch.');
            $this->redirect('/login');
        }

        if ($code === '') {
            $this->session->addFlash(
                'error',
                'OAuth authorization was cancelled or failed.'
            );
            $this->redirect('/login');
        }

        $callback_result = $this->oauth_service->handleCallback(
            $provider,
            $code
        );

        if (!$callback_result['success']) {
            $this->session->addFlash(
                'error',
                $callback_result['error'] ?? 'Failed to authenticate with OAuth provider.'
            );
            $this->redirect('/login');
        }

        $find_result = $this->oauth_service->findOrCreateUser(
            $provider,
            $callback_result
        );

        if (!$find_result['success']) {
            $this->session->addFlash(
                'error',
                $find_result['error'] ?? 'Failed to create account.'
            );
            $this->redirect('/login');
        }

        if ($find_result['new_user'] && $find_result['needs_username']) {
            $_SESSION['oauth_pending'] = [
                'provider'   => $provider,
                'oauth_data' => $find_result['oauth_data'],
            ];

            $suggested_username = $this->oauth_service->generateUsername(
                $find_result['oauth_data']['name'] ?? null,
                $find_result['oauth_data']['email'] ?? null
            );

            $result = $this->renderThemed('pages/oauth_complete.html.twig', [
                'provider'            => $provider,
                'suggested_username'  => $suggested_username,
                'email'               => $find_result['oauth_data']['email'] ?? '',
                'name'                => $find_result['oauth_data']['name'] ?? '',
            ]);
        } else {
            $this->session->login($find_result['user']);
            $this->session->addFlash('success', 'Welcome back!');
            $this->redirect('/');
        }

        return $result;
    }

    /**
     * Completes OAuth registration by setting username.
     *
     * POST /oauth/complete
     *
     * @return string The rendered HTML or redirect.
     */
    public function complete(): string {
        $result = '';

        $this->requireGuest();

        if (!isset($_SESSION['oauth_pending'])) {
            $this->redirect('/login');
        }

        $oauth_pending = $_SESSION['oauth_pending'];
        $provider = $oauth_pending['provider'];
        $oauth_data = $oauth_pending['oauth_data'];

        if (!$this->validateCsrf()) {
            $this->session->addFlash('error', 'Invalid form submission.');
            $this->redirect('/login');
        }

        $username = trim((string) $this->getPost('username', ''));

        $create_result = $this->oauth_service->createUserFromOAuth(
            $provider,
            $oauth_data,
            $username
        );

        if (!$create_result['success']) {
            $suggested_username = $this->oauth_service->generateUsername(
                $oauth_data['name'] ?? null,
                $oauth_data['email'] ?? null
            );

            $result = $this->renderThemed('pages/oauth_complete.html.twig', [
                'provider'            => $provider,
                'suggested_username'  => $suggested_username,
                'email'               => $oauth_data['email'] ?? '',
                'name'                => $oauth_data['name'] ?? '',
                'username'            => $username,
                'error'               => $create_result['error'],
            ]);
        } else {
            unset($_SESSION['oauth_pending']);

            if (!empty($create_result['pending'])) {
                $this->session->addFlash(
                    'success',
                    'Your account has been created and is awaiting admin approval.'
                );
                $this->redirect('/login');
            } else {
                $this->session->login($create_result['user']);
                $this->session->addFlash('success', 'Welcome to Murmur!');
                $this->redirect('/');
            }
        }

        return $result;
    }

    /**
     * Unlinks an OAuth provider from the current user's account.
     *
     * POST /oauth/{provider}/unlink
     *
     * @param string $provider The provider name.
     *
     * @return void
     */
    public function unlink(string $provider): void {
        $this->requireAuth();

        if (!$this->validateCsrf()) {
            $this->session->addFlash('error', 'Invalid form submission.');
            $this->redirect('/settings/connected-accounts');
        }

        $current_user = $this->session->getCurrentUser();

        if ($current_user === null) {
            $this->redirect('/login');
        }

        $unlink_result = $this->oauth_service->unlinkProvider(
            $current_user->user_id,
            $provider
        );

        if ($unlink_result['success']) {
            $this->session->addFlash(
                'success',
                ucfirst($provider) . ' account has been unlinked.'
            );
        } else {
            $this->session->addFlash(
                'error',
                $unlink_result['error'] ?? 'Failed to unlink account.'
            );
        }

        $this->redirect('/settings/connected-accounts');
    }
}
