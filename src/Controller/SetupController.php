<?php

declare(strict_types=1);

namespace Murmur\Controller;

use Murmur\Repository\SettingMapper;
use Murmur\Service\AuthService;
use Murmur\Service\SessionService;
use Twig\Environment;

/**
 * Controller for first-run setup.
 *
 * Handles initial admin account creation when no users exist.
 */
class SetupController extends BaseController {

    /**
     * Authentication service.
     */
    protected AuthService $auth;

    /**
     * Creates a new SetupController instance.
     *
     * @param Environment    $twig           Twig environment for rendering.
     * @param SessionService $session        Session service.
     * @param SettingMapper  $setting_mapper Setting mapper.
     * @param AuthService    $auth           Authentication service.
     */
    public function __construct(Environment $twig, SessionService $session, SettingMapper $setting_mapper, AuthService $auth) {
        parent::__construct($twig, $session, $setting_mapper);
        $this->auth = $auth;
    }

    /**
     * Displays the setup form.
     *
     * GET /setup
     *
     * @return string The rendered HTML.
     */
    public function showSetupForm(): string {
        return $this->renderThemed('pages/setup.html.twig');
    }

    /**
     * Handles setup form submission.
     *
     * POST /setup
     *
     * @return string The rendered HTML or redirect.
     */
    public function setup(): string {
        $result = '';

        if (!$this->validateCsrf()) {
            $this->session->addFlash('error', 'Invalid form submission. Please try again.');
            $result = $this->renderThemed('pages/setup.html.twig');
        } else {
            $name = trim((string) $this->getPost('name', ''));
            $username = trim((string) $this->getPost('username', ''));
            $email = trim((string) $this->getPost('email', ''));
            $password = (string) $this->getPost('password', '');
            $password_confirm = (string) $this->getPost('password_confirm', '');

            if ($password !== $password_confirm) {
                $result = $this->renderThemed('pages/setup.html.twig', [
                    'error' => 'Passwords do not match.',
                    'name' => $name,
                    'username' => $username,
                    'email' => $email,
                ]);
            } else {
                $auth_result = $this->auth->register($username, $email, $password, true, $name);

                if ($auth_result['success']) {
                    // Auto-detect and save base URL for subdirectory installations
                    $base_url = $this->detectBaseUrl();
                    $this->setting_mapper->saveSetting('base_url', $base_url);

                    $this->session->login($auth_result['user']);
                    $this->session->addFlash('success', 'Welcome to Murmur! Your admin account has been created.');
                    $this->redirect('/');
                } else {
                    $result = $this->renderThemed('pages/setup.html.twig', [
                        'error' => $auth_result['error'],
                        'name' => $name,
                        'username' => $username,
                        'email' => $email,
                    ]);
                }
            }
        }

        return $result;
    }

    /**
     * Detects the base URL from the current request.
     *
     * @return string The base URL (e.g., '/murmur') or empty string for root.
     */
    protected function detectBaseUrl(): string {
        $base_url = '';

        $script_name = $_SERVER['SCRIPT_NAME'] ?? '';

        // Remove /index.php from the end if present
        $base_path = dirname($script_name);

        // Normalize: remove trailing slash, handle root case
        if ($base_path === '/' || $base_path === '\\' || $base_path === '.') {
            $base_url = '';
        } else {
            $base_url = rtrim($base_path, '/');
        }

        return $base_url;
    }
}
