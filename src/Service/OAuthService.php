<?php

declare(strict_types=1);

namespace Murmur\Service;

use League\OAuth2\Client\Provider\AbstractProvider;
use League\OAuth2\Client\Provider\Facebook;
use League\OAuth2\Client\Provider\Google;
use League\OAuth2\Client\Provider\ResourceOwnerInterface;
use Murmur\Entity\User;
use Murmur\Entity\UserOAuthProvider;
use Murmur\Repository\SettingMapper;
use Murmur\Repository\UserMapper;
use Murmur\Repository\UserOAuthProviderMapper;
use PatrickBussmann\OAuth2\Client\Provider\Apple;

/**
 * Service for OAuth authentication operations.
 *
 * Handles OAuth provider connections, user authentication via OAuth,
 * and account linking.
 */
class OAuthService {

    /**
     * OAuth configuration service.
     */
    protected OAuthConfigService $oauth_config;

    /**
     * User mapper for database operations.
     */
    protected UserMapper $user_mapper;

    /**
     * OAuth provider mapper for database operations.
     */
    protected UserOAuthProviderMapper $oauth_mapper;

    /**
     * Setting mapper for checking admin settings.
     */
    protected SettingMapper $setting_mapper;

    /**
     * Creates a new OAuthService instance.
     *
     * @param OAuthConfigService       $oauth_config OAuth configuration.
     * @param UserMapper               $user_mapper  User mapper.
     * @param UserOAuthProviderMapper  $oauth_mapper OAuth provider mapper.
     * @param SettingMapper            $setting_mapper Setting mapper.
     */
    public function __construct(
        OAuthConfigService $oauth_config,
        UserMapper $user_mapper,
        UserOAuthProviderMapper $oauth_mapper,
        SettingMapper $setting_mapper
    ) {
        $this->oauth_config = $oauth_config;
        $this->user_mapper = $user_mapper;
        $this->oauth_mapper = $oauth_mapper;
        $this->setting_mapper = $setting_mapper;
    }

    /**
     * Gets an OAuth provider client instance.
     *
     * @param string $provider The provider name (google, facebook, apple).
     *
     * @return AbstractProvider|null The provider client or null if not configured.
     */
    public function getProvider(string $provider): ?AbstractProvider {
        $result = null;

        if (!in_array($provider, OAuthConfigService::PROVIDERS, true)) {
            return $result;
        }

        if (!$this->oauth_config->isConfigured($provider)) {
            return $result;
        }

        if ($provider === 'google') {
            $result = new Google([
                'clientId'     => $this->oauth_config->getClientId($provider),
                'clientSecret' => $this->oauth_config->getClientSecret($provider),
                'redirectUri'  => $this->oauth_config->getRedirectUri($provider),
            ]);
        } elseif ($provider === 'facebook') {
            $result = new Facebook([
                'clientId'        => $this->oauth_config->getClientId($provider),
                'clientSecret'    => $this->oauth_config->getClientSecret($provider),
                'redirectUri'     => $this->oauth_config->getRedirectUri($provider),
                'graphApiVersion' => 'v18.0',
            ]);
        } elseif ($provider === 'apple') {
            $result = new Apple([
                'clientId'          => $this->oauth_config->getClientId($provider),
                'teamId'            => $this->oauth_config->getAppleTeamId(),
                'keyFileId'         => $this->oauth_config->getAppleKeyId(),
                'keyFilePath'       => $this->oauth_config->getApplePrivateKeyPath(),
                'redirectUri'       => $this->oauth_config->getRedirectUri($provider),
            ]);
        }

        return $result;
    }

    /**
     * Checks if an OAuth provider is enabled in admin settings.
     *
     * @param string $provider The provider name.
     *
     * @return bool True if the provider is enabled.
     */
    public function isProviderEnabled(string $provider): bool {
        $setting_key = "oauth_{$provider}_enabled";
        $setting = $this->setting_mapper->load($setting_key);

        $result = false;

        if ($setting !== null && $setting->setting_value === '1') {
            $result = true;
        }

        return $result;
    }

    /**
     * Checks if an OAuth provider is configured.
     *
     * A provider is considered configured if it has the required
     * credentials set in config.ini.
     *
     * @param string $provider The provider name (google, facebook, apple).
     *
     * @return bool True if the provider has credentials configured.
     */
    public function isProviderConfigured(string $provider): bool {
        return $this->oauth_config->isConfigured($provider);
    }

    /**
     * Gets the authorization URL for an OAuth provider.
     *
     * @param string $provider The provider name.
     * @param string $state    CSRF state token.
     *
     * @return string|null The authorization URL or null if provider unavailable.
     */
    public function getAuthorizationUrl(
        string $provider,
        string $state
    ): ?string {
        $result = null;

        $oauth_provider = $this->getProvider($provider);

        if ($oauth_provider !== null) {
            $options = ['state' => $state];

            if ($provider === 'google') {
                $options['scope'] = ['email', 'profile'];
            } elseif ($provider === 'facebook') {
                $options['scope'] = ['email', 'public_profile'];
            } elseif ($provider === 'apple') {
                $options['scope'] = ['email', 'name'];
            }

            $result = $oauth_provider->getAuthorizationUrl($options);
        }

        return $result;
    }

    /**
     * Handles OAuth callback and returns user data.
     *
     * @param string $provider The provider name.
     * @param string $code     Authorization code from provider.
     *
     * @return array{success: bool, provider_user_id?: string, email?: string, name?: string, error?: string}
     */
    public function handleCallback(string $provider, string $code): array {
        $result = ['success' => false];

        $oauth_provider = $this->getProvider($provider);

        if ($oauth_provider === null) {
            $result['error'] = 'OAuth provider not configured.';

            return $result;
        }

        try {
            $token = $oauth_provider->getAccessToken('authorization_code', [
                'code' => $code,
            ]);

            $resource_owner = $oauth_provider->getResourceOwner($token);

            $result = $this->extractUserData($provider, $resource_owner);
            $result['success'] = true;
        } catch (\Throwable $e) {
            $result['error'] = 'Failed to authenticate with OAuth provider.';
        }

        return $result;
    }

    /**
     * Extracts user data from OAuth resource owner.
     *
     * @param string                   $provider OAuth provider name.
     * @param ResourceOwnerInterface   $resource_owner Resource owner from provider.
     *
     * @return array{provider_user_id: string, email?: string, name?: string}
     */
    protected function extractUserData(
        string $provider,
        ResourceOwnerInterface $resource_owner
    ): array {
        $data = $resource_owner->toArray();
        $result = ['provider_user_id' => (string) $resource_owner->getId()];

        if ($provider === 'google') {
            $result['email'] = $data['email'] ?? null;
            $result['name'] = $data['name'] ?? null;
        } elseif ($provider === 'facebook') {
            $result['email'] = $data['email'] ?? null;
            $result['name'] = $data['name'] ?? null;
        } elseif ($provider === 'apple') {
            $result['email'] = $data['email'] ?? null;

            if (isset($data['name']['firstName']) || isset($data['name']['lastName'])) {
                $first_name = $data['name']['firstName'] ?? '';
                $last_name = $data['name']['lastName'] ?? '';
                $result['name'] = trim($first_name . ' ' . $last_name);
            }
        }

        return $result;
    }

    /**
     * Finds or creates a user from OAuth data.
     *
     * @param string $provider Provider name.
     * @param array  $oauth_data OAuth user data from handleCallback.
     *
     * @return array{success: bool, user?: User, new_user?: bool, needs_username?: bool, oauth_provider?: UserOAuthProvider, error?: string}
     */
    public function findOrCreateUser(string $provider, array $oauth_data): array {
        $result = ['success' => false];

        $provider_user_id = $oauth_data['provider_user_id'] ?? null;
        $email = $oauth_data['email'] ?? null;
        $name = $oauth_data['name'] ?? null;

        if ($provider_user_id === null) {
            $result['error'] = 'Invalid OAuth data.';

            return $result;
        }

        $existing_oauth = $this->oauth_mapper->findByProviderUserId(
            $provider,
            $provider_user_id
        );

        if ($existing_oauth !== null) {
            $user = $this->user_mapper->load($existing_oauth->user_id);

            if ($user !== null && !$user->is_disabled && !$user->is_pending) {
                $result['success'] = true;
                $result['user'] = $user;
                $result['new_user'] = false;
                $result['needs_username'] = false;

                return $result;
            }

            if ($user !== null && $user->is_disabled) {
                $result['error'] = 'This account has been disabled.';

                return $result;
            }

            if ($user !== null && $user->is_pending) {
                $result['error'] = 'Your account is awaiting admin approval.';

                return $result;
            }
        }

        if ($email === null) {
            $result['error'] = 'Email address is required.';
            $result['needs_email'] = true;

            return $result;
        }

        $existing_user = $this->user_mapper->findByEmail($email);

        if ($existing_user !== null) {
            $oauth_provider = new UserOAuthProvider();
            $oauth_provider->user_id = $existing_user->user_id;
            $oauth_provider->provider = $provider;
            $oauth_provider->provider_user_id = $provider_user_id;
            $oauth_provider->email = $email;
            $oauth_provider->name = $name;

            $this->oauth_mapper->save($oauth_provider);

            $result['success'] = true;
            $result['user'] = $existing_user;
            $result['new_user'] = false;
            $result['needs_username'] = false;

            return $result;
        }

        $result['success'] = true;
        $result['new_user'] = true;
        $result['needs_username'] = true;
        $result['oauth_data'] = $oauth_data;

        return $result;
    }

    /**
     * Creates a new user from OAuth data with a username.
     *
     * @param string $provider Provider name.
     * @param array  $oauth_data OAuth user data.
     * @param string $username Chosen username.
     *
     * @return array{success: bool, user?: User, error?: string}
     */
    public function createUserFromOAuth(
        string $provider,
        array $oauth_data,
        string $username
    ): array {
        $result = ['success' => false];

        $provider_user_id = $oauth_data['provider_user_id'] ?? null;
        $email = $oauth_data['email'] ?? null;
        $name = $oauth_data['name'] ?? null;

        if ($provider_user_id === null || $email === null) {
            $result['error'] = 'Invalid OAuth data.';

            return $result;
        }

        $username = trim($username);

        if (strlen($username) < 3 || strlen($username) > 30) {
            $result['error'] = 'Username must be 3-30 characters.';

            return $result;
        }

        if (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
            $result['error'] = 'Username can only contain letters, numbers, and underscores.';

            return $result;
        }

        $existing_user = $this->user_mapper->findByUsername($username);

        if ($existing_user !== null) {
            $result['error'] = 'Username is already taken.';

            return $result;
        }

        $user = new User();
        $user->username = $username;
        $user->email = $email;
        $user->name = $name ?? $username;
        $user->password_hash = null;
        $user->is_admin = false;

        $requires_approval = $this->setting_mapper->isApprovalRequired();
        $user->is_pending = $requires_approval;

        $this->user_mapper->save($user);

        $oauth_provider = new UserOAuthProvider();
        $oauth_provider->user_id = $user->user_id;
        $oauth_provider->provider = $provider;
        $oauth_provider->provider_user_id = $provider_user_id;
        $oauth_provider->email = $email;
        $oauth_provider->name = $name;

        $this->oauth_mapper->save($oauth_provider);

        $result['success'] = true;
        $result['user'] = $user;
        $result['pending'] = $requires_approval;

        return $result;
    }

    /**
     * Links an OAuth provider to an existing user.
     *
     * @param int    $user_id User ID.
     * @param string $provider Provider name.
     * @param array  $oauth_data OAuth user data.
     *
     * @return array{success: bool, error?: string}
     */
    public function linkProvider(
        int $user_id,
        string $provider,
        array $oauth_data
    ): array {
        $result = ['success' => false];

        $provider_user_id = $oauth_data['provider_user_id'] ?? null;
        $email = $oauth_data['email'] ?? null;
        $name = $oauth_data['name'] ?? null;

        if ($provider_user_id === null) {
            $result['error'] = 'Invalid OAuth data.';

            return $result;
        }

        $existing_oauth = $this->oauth_mapper->findByUserAndProvider(
            $user_id,
            $provider
        );

        if ($existing_oauth !== null) {
            $result['error'] = 'This provider is already linked to your account.';

            return $result;
        }

        $oauth_provider = new UserOAuthProvider();
        $oauth_provider->user_id = $user_id;
        $oauth_provider->provider = $provider;
        $oauth_provider->provider_user_id = $provider_user_id;
        $oauth_provider->email = $email;
        $oauth_provider->name = $name;

        $this->oauth_mapper->save($oauth_provider);

        $result['success'] = true;

        return $result;
    }

    /**
     * Unlinks an OAuth provider from a user.
     *
     * @param int    $user_id User ID.
     * @param string $provider Provider name.
     *
     * @return array{success: bool, error?: string}
     */
    public function unlinkProvider(int $user_id, string $provider): array {
        $result = ['success' => false];

        $can_unlink = $this->canUnlinkProvider($user_id, $provider);

        if (!$can_unlink['can_unlink']) {
            $result['error'] = $can_unlink['reason'];

            return $result;
        }

        $unlinked = $this->oauth_mapper->unlinkProvider($user_id, $provider);

        if ($unlinked) {
            $result['success'] = true;
        } else {
            $result['error'] = 'Provider is not linked to your account.';
        }

        return $result;
    }

    /**
     * Checks if a user can unlink an OAuth provider.
     *
     * @param int    $user_id User ID.
     * @param string $provider Provider name.
     *
     * @return array{can_unlink: bool, reason?: string}
     */
    public function canUnlinkProvider(int $user_id, string $provider): array {
        $result = ['can_unlink' => false];

        $user = $this->user_mapper->load($user_id);

        if ($user === null) {
            $result['reason'] = 'User not found.';

            return $result;
        }

        $linked_providers = $this->oauth_mapper->findByUser($user_id);

        if (count($linked_providers) === 1 && $user->password_hash === null) {
            $result['reason'] = 'Cannot unlink last sign-in method. Set a password first.';

            return $result;
        }

        $result['can_unlink'] = true;

        return $result;
    }

    /**
     * Generates a unique username from name or email.
     *
     * @param string|null $name Name from OAuth provider.
     * @param string|null $email Email from OAuth provider.
     *
     * @return string A unique username suggestion.
     */
    public function generateUsername(?string $name, ?string $email): string {
        $base = '';

        if ($name !== null && $name !== '') {
            $base = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $name));
        } elseif ($email !== null) {
            $parts = explode('@', $email, 2);
            $base = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $parts[0]));
        }

        if ($base === '' || strlen($base) < 3) {
            $base = 'user';
        }

        if (strlen($base) > 25) {
            $base = substr($base, 0, 25);
        }

        $username = $base;
        $existing = $this->user_mapper->findByUsername($username);

        $counter = 1;

        while ($existing !== null && $counter < 100) {
            $username = $base . $counter;
            $existing = $this->user_mapper->findByUsername($username);
            $counter++;
        }

        if ($existing !== null) {
            $username = $base . '_' . bin2hex(random_bytes(4));
        }

        return $username;
    }
}
