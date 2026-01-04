<?php

declare(strict_types=1);

namespace Murmur\Entity;

use Moonspot\ValueObjects\ValueObject;

/**
 * UserOAuthProvider entity representing an OAuth provider connection.
 *
 * Maps to the `user_oauth_providers` table in the database.
 * Tracks which OAuth providers (Google, Facebook, Apple) are linked to a user account.
 */
class UserOAuthProvider extends ValueObject {

    /**
     * Primary key identifier for the OAuth connection.
     */
    public ?int $oauth_id = null;

    /**
     * User ID this OAuth provider is linked to.
     */
    public int $user_id = 0;

    /**
     * OAuth provider name (google, facebook, apple).
     */
    public string $provider = '';

    /**
     * Unique user ID from the OAuth provider.
     */
    public string $provider_user_id = '';

    /**
     * Email address returned by the OAuth provider.
     */
    public ?string $email = null;

    /**
     * Name returned by the OAuth provider.
     */
    public ?string $name = null;

    /**
     * Timestamp when the OAuth connection was created.
     */
    public ?string $created_at = null;

    /**
     * Timestamp when the OAuth connection was last updated.
     */
    public ?string $updated_at = null;
}
