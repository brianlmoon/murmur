<?php

declare(strict_types=1);

namespace Murmur\Entity;

use Moonspot\ValueObjects\ValueObject;

/**
 * User entity representing a registered user in the system.
 *
 * Maps to the `users` table in the database.
 */
class User extends ValueObject {

    /**
     * Primary key identifier for the user.
     */
    public ?int $user_id = null;

    /**
     * Unique display name for the user.
     */
    public string $username = '';

    /**
     * Optional full/real name of the user.
     */
    public ?string $name = null;

    /**
     * Email address used for login and notifications.
     */
    public string $email = '';

    /**
     * Hashed password (Bcrypt or Argon2).
     */
    public string $password_hash = '';

    /**
     * Optional profile bio/description.
     */
    public ?string $bio = null;

    /**
     * Path to the user's avatar image.
     */
    public ?string $avatar_path = null;

    /**
     * Whether the user has admin privileges.
     */
    public bool $is_admin = false;

    /**
     * Whether the account has been disabled by an admin.
     */
    public bool $is_disabled = false;

    /**
     * Whether the account is pending admin approval.
     */
    public bool $is_pending = false;

    /**
     * Timestamp when the user was created.
     */
    public ?string $created_at = null;

    /**
     * Timestamp when the user was last updated.
     */
    public ?string $updated_at = null;
}
