<?php

declare(strict_types=1);

namespace Murmur\Service;

use DealNews\GetConfig\GetConfig;

/**
 * Service for managing OAuth configuration.
 *
 * Reads OAuth provider credentials and settings from config.ini.
 */
class OAuthConfigService {

    /**
     * Available OAuth providers.
     */
    public const PROVIDERS = ['google', 'facebook', 'apple'];

    /**
     * Configuration instance.
     */
    protected GetConfig $config;

    /**
     * Base URL for the application.
     */
    protected string $base_url;

    /**
     * Creates a new OAuthConfigService instance.
     *
     * @param GetConfig $config   Configuration instance.
     * @param string    $base_url Base URL for the application.
     */
    public function __construct(GetConfig $config, string $base_url) {
        $this->config = $config;
        $this->base_url = $base_url;
    }

    /**
     * Gets the client ID for a provider.
     *
     * @param string $provider The provider name (google, facebook, apple).
     *
     * @return string|null The client ID or null if not configured.
     */
    public function getClientId(string $provider): ?string {
        $key = "oauth.{$provider}.client_id";
        $value = $this->config->get($key);

        $result = null;

        if (is_string($value) && $value !== '') {
            $result = $value;
        }

        return $result;
    }

    /**
     * Gets the client secret for a provider.
     *
     * @param string $provider The provider name (google, facebook, apple).
     *
     * @return string|null The client secret or null if not configured.
     */
    public function getClientSecret(string $provider): ?string {
        $key = "oauth.{$provider}.client_secret";
        $value = $this->config->get($key);

        $result = null;

        if (is_string($value) && $value !== '') {
            $result = $value;
        }

        return $result;
    }

    /**
     * Gets the redirect URI for a provider.
     *
     * @param string $provider The provider name (google, facebook, apple).
     *
     * @return string The full redirect URI for OAuth callbacks.
     */
    public function getRedirectUri(string $provider): string {
        return $this->base_url . "/oauth/{$provider}/callback";
    }

    /**
     * Checks if a provider is configured with credentials.
     *
     * @param string $provider The provider name (google, facebook, apple).
     *
     * @return bool True if the provider has credentials configured.
     */
    public function isConfigured(string $provider): bool {
        $result = false;

        if ($provider === 'apple') {
            $result = $this->getClientId($provider) !== null &&
                      $this->getAppleTeamId() !== null &&
                      $this->getAppleKeyId() !== null &&
                      $this->getApplePrivateKeyPath() !== null;
        } else {
            $result = $this->getClientId($provider) !== null &&
                      $this->getClientSecret($provider) !== null;
        }

        return $result;
    }

    /**
     * Gets the Apple Team ID.
     *
     * @return string|null The Apple Team ID or null if not configured.
     */
    public function getAppleTeamId(): ?string {
        $value = $this->config->get('oauth.apple.team_id');

        $result = null;

        if (is_string($value) && $value !== '') {
            $result = $value;
        }

        return $result;
    }

    /**
     * Gets the Apple Key ID.
     *
     * @return string|null The Apple Key ID or null if not configured.
     */
    public function getAppleKeyId(): ?string {
        $value = $this->config->get('oauth.apple.key_id');

        $result = null;

        if (is_string($value) && $value !== '') {
            $result = $value;
        }

        return $result;
    }

    /**
     * Gets the path to the Apple private key file.
     *
     * @return string|null The private key file path or null.
     */
    public function getApplePrivateKeyPath(): ?string {
        $value = $this->config->get('oauth.apple.private_key_path');

        $result = null;

        if (is_string($value) && $value !== '') {
            $result = $value;
        }

        return $result;
    }

    /**
     * Gets all available OAuth providers.
     *
     * @return string[] Array of provider names.
     */
    public function getAvailableProviders(): array {
        return self::PROVIDERS;
    }
}
