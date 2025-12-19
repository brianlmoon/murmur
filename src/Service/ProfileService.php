<?php

declare(strict_types=1);

namespace Murmur\Service;

use Murmur\Entity\User;
use Murmur\Repository\UserMapper;

/**
 * Service for user profile operations.
 *
 * Handles profile retrieval and updates.
 */
class ProfileService {

    /**
     * Maximum length for bio.
     */
    protected const MAX_BIO_LENGTH = 160;

    /**
     * Maximum length for name.
     */
    protected const MAX_NAME_LENGTH = 100;

    /**
     * The user mapper for database operations.
     */
    protected UserMapper $user_mapper;

    /**
     * Creates a new ProfileService instance.
     *
     * @param UserMapper $user_mapper The user mapper.
     */
    public function __construct(UserMapper $user_mapper) {
        $this->user_mapper = $user_mapper;
    }

    /**
     * Gets a user profile by username.
     *
     * @param string $username The username to look up.
     *
     * @return User|null The user or null if not found.
     */
    public function getByUsername(string $username): ?User {
        return $this->user_mapper->findByUsername($username);
    }

    /**
     * Updates a user's profile.
     *
     * @param User        $user        The user to update.
     * @param string      $username    The new username.
     * @param string      $email       The new email.
     * @param string|null $bio         The new bio.
     * @param string|null $avatar_path The new avatar path (null to keep existing).
     * @param string|null $name        The new display name.
     *
     * @return array{success: bool, error?: string}
     */
    public function updateProfile(
        User $user,
        string $username,
        string $email,
        ?string $bio,
        ?string $avatar_path = null,
        ?string $name = null
    ): array {
        $result = ['success' => false];

        $username = trim($username);
        $email = trim(strtolower($email));
        $bio = $bio !== null ? trim($bio) : null;
        $name = $name !== null ? trim($name) : null;

        $validation_error = $this->validateProfile($username, $email, $bio, $name);

        if ($validation_error !== null) {
            $result['error'] = $validation_error;
        } else {
            // Check if username is taken by another user
            $existing = $this->user_mapper->findByUsername($username);

            if ($existing !== null && $existing->user_id !== $user->user_id) {
                $result['error'] = 'Username is already taken.';
            } else {
                // Check if email is taken by another user
                $existing = $this->user_mapper->findByEmail($email);

                if ($existing !== null && $existing->user_id !== $user->user_id) {
                    $result['error'] = 'Email is already in use.';
                } else {
                    $user->username = $username;
                    $user->email = $email;
                    $user->bio = $bio !== '' ? $bio : null;
                    $user->name = $name !== '' ? $name : null;

                    if ($avatar_path !== null) {
                        $user->avatar_path = $avatar_path;
                    }

                    $this->user_mapper->save($user);

                    $result['success'] = true;
                }
            }
        }

        return $result;
    }

    /**
     * Removes a user's avatar.
     *
     * @param User $user The user.
     *
     * @return string|null The old avatar path (for deletion) or null.
     */
    public function removeAvatar(User $user): ?string {
        $old_path = $user->avatar_path;

        $user->avatar_path = null;
        $this->user_mapper->save($user);

        return $old_path;
    }

    /**
     * Validates profile data.
     *
     * @param string      $username The username.
     * @param string      $email    The email.
     * @param string|null $bio      The bio.
     * @param string|null $name     The display name.
     *
     * @return string|null Error message or null if valid.
     */
    protected function validateProfile(string $username, string $email, ?string $bio, ?string $name = null): ?string {
        $error = null;

        if (strlen($username) < 3) {
            $error = 'Username must be at least 3 characters.';
        } elseif (strlen($username) > 30) {
            $error = 'Username must be 30 characters or less.';
        } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
            $error = 'Username can only contain letters, numbers, and underscores.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } elseif ($bio !== null && mb_strlen($bio) > self::MAX_BIO_LENGTH) {
            $error = 'Bio cannot exceed ' . self::MAX_BIO_LENGTH . ' characters.';
        } elseif ($name === null || $name === '') {
            $error = 'Name is required.';
        } elseif (mb_strlen($name) > self::MAX_NAME_LENGTH) {
            $error = 'Name cannot exceed ' . self::MAX_NAME_LENGTH . ' characters.';
        }

        return $error;
    }

    /**
     * Updates a user's password.
     *
     * @param User   $user             The user.
     * @param string $current_password The current password.
     * @param string $new_password     The new password.
     *
     * @return array{success: bool, error?: string}
     */
    public function updatePassword(User $user, string $current_password, string $new_password): array {
        $result = ['success' => false];

        if (!password_verify($current_password, $user->password_hash)) {
            $result['error'] = 'Current password is incorrect.';
        } elseif (strlen($new_password) < 8) {
            $result['error'] = 'New password must be at least 8 characters.';
        } else {
            $algorithm = PASSWORD_ARGON2ID;

            if (!defined('PASSWORD_ARGON2ID')) {
                $algorithm = PASSWORD_BCRYPT;
            }

            $user->password_hash = password_hash($new_password, $algorithm);
            $this->user_mapper->save($user);

            $result['success'] = true;
        }

        return $result;
    }

    /**
     * Gets the maximum bio length.
     *
     * @return int The maximum length.
     */
    public function getMaxBioLength(): int {
        return self::MAX_BIO_LENGTH;
    }
}
