<?php

declare(strict_types=1);

namespace Murmur\Repository;

use DealNews\DB\AbstractMapper;
use Murmur\Entity\UserOAuthProvider;

/**
 * Data Mapper for the UserOAuthProvider entity.
 *
 * Handles persistence operations for the `user_oauth_providers` table.
 */
class UserOAuthProviderMapper extends AbstractMapper {

    /**
     * Database configuration name.
     */
    public const DATABASE_NAME = 'murmur';

    /**
     * Database table name.
     */
    public const TABLE = 'user_oauth_providers';

    /**
     * Primary key column name.
     */
    public const PRIMARY_KEY = 'oauth_id';

    /**
     * Value object class for this mapper.
     */
    public const MAPPED_CLASS = UserOAuthProvider::class;

    /**
     * Column mappings from database to entity properties.
     *
     * @var array<string, array>
     */
    public const MAPPING = [
        'oauth_id'         => [],
        'user_id'          => [],
        'provider'         => [],
        'provider_user_id' => [],
        'email'            => [],
        'name'             => [],
        'created_at'       => ['read_only' => true],
        'updated_at'       => ['read_only' => true],
    ];

    /**
     * Finds an OAuth provider connection by user and provider name.
     *
     * @param int    $user_id  The user ID.
     * @param string $provider The provider name (google, facebook, apple).
     *
     * @return UserOAuthProvider|null The OAuth provider entity or null.
     */
    public function findByUserAndProvider(
        int $user_id,
        string $provider
    ): ?UserOAuthProvider {
        $result = null;

        $providers = $this->find(
            [
                'user_id'  => $user_id,
                'provider' => $provider,
            ],
            1
        );

        if (!empty($providers)) {
            $result = reset($providers);
        }

        return $result;
    }

    /**
     * Finds an OAuth provider connection by provider and provider user ID.
     *
     * @param string $provider         The provider name.
     * @param string $provider_user_id The unique user ID from the provider.
     *
     * @return UserOAuthProvider|null The OAuth provider entity or null.
     */
    public function findByProviderUserId(
        string $provider,
        string $provider_user_id
    ): ?UserOAuthProvider {
        $result = null;

        $providers = $this->find(
            [
                'provider'         => $provider,
                'provider_user_id' => $provider_user_id,
            ],
            1
        );

        if (!empty($providers)) {
            $result = reset($providers);
        }

        return $result;
    }

    /**
     * Finds all OAuth provider connections for a user.
     *
     * @param int $user_id The user ID.
     *
     * @return UserOAuthProvider[] Array of OAuth provider entities.
     */
    public function findByUser(int $user_id): array {
        return $this->find(['user_id' => $user_id]);
    }

    /**
     * Unlinks an OAuth provider from a user.
     *
     * @param int    $user_id  The user ID.
     * @param string $provider The provider name.
     *
     * @return bool True if a provider was unlinked, false otherwise.
     */
    public function unlinkProvider(int $user_id, string $provider): bool {
        $result = false;

        $oauth_provider = $this->findByUserAndProvider($user_id, $provider);

        if ($oauth_provider !== null && $oauth_provider->oauth_id !== null) {
            $this->delete($oauth_provider->oauth_id);
            $result = true;
        }

        return $result;
    }
}
