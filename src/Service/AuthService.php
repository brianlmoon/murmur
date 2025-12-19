<?php

declare(strict_types=1);

namespace Murmur\Service;

use Murmur\Entity\User;
use Murmur\Repository\SettingMapper;
use Murmur\Repository\UserMapper;

/**
 * Service for authentication operations.
 *
 * Handles user registration, login validation, and password hashing.
 */
class AuthService {

    /**
     * The user mapper for database operations.
     */
    protected UserMapper $user_mapper;

    /**
     * The setting mapper for checking approval settings.
     */
    protected SettingMapper $setting_mapper;

    /**
     * Creates a new AuthService instance.
     *
     * @param UserMapper    $user_mapper    The user mapper for database operations.
     * @param SettingMapper $setting_mapper The setting mapper for checking settings.
     */
    public function __construct(UserMapper $user_mapper, SettingMapper $setting_mapper) {
        $this->user_mapper = $user_mapper;
        $this->setting_mapper = $setting_mapper;
    }

    /**
     * Registers a new user.
     *
     * @param string $username The desired username.
     * @param string $email    The user's email address.
     * @param string $password The plaintext password.
     * @param bool   $is_admin Whether this user should be an admin.
     * @param string $name     The user's full name.
     *
     * @return array{success: bool, user?: User, error?: string, pending?: bool}
     */
    public function register(string $username, string $email, string $password, bool $is_admin = false, string $name = ''): array {
        $result = ['success' => false];

        $username = trim($username);
        $email = trim(strtolower($email));
        $name = trim($name);

        // Validate inputs
        $validation_error = $this->validateRegistration($username, $email, $password, $name);

        if ($validation_error !== null) {
            $result['error'] = $validation_error;
        } else {
            // Check if username already exists
            $existing_user = $this->user_mapper->findByUsername($username);

            if ($existing_user !== null) {
                $result['error'] = 'Username is already taken.';
            } else {
                // Check if email already exists
                $existing_user = $this->user_mapper->findByEmail($email);

                if ($existing_user !== null) {
                    $result['error'] = 'Email is already registered.';
                } else {
                    // Create new user
                    $user = new User();
                    $user->username = $username;
                    $user->email = $email;
                    $user->password_hash = $this->hashPassword($password);
                    $user->is_admin = $is_admin;
                    $user->name = $name;

                    // Check if approval is required (admins bypass this)
                    $requires_approval = !$is_admin && $this->setting_mapper->isApprovalRequired();
                    $user->is_pending = $requires_approval;

                    $this->user_mapper->save($user);

                    $result['success'] = true;
                    $result['user'] = $user;
                    $result['pending'] = $requires_approval;
                }
            }
        }

        return $result;
    }

    /**
     * Validates registration inputs.
     *
     * @param string $username The username to validate.
     * @param string $email    The email to validate.
     * @param string $password The password to validate.
     * @param string $name     The name to validate.
     *
     * @return string|null Error message or null if valid.
     */
    protected function validateRegistration(string $username, string $email, string $password, string $name = ''): ?string {
        $error = null;

        if ($name === '') {
            $error = 'Name is required.';
        } elseif (strlen($name) > 100) {
            $error = 'Name cannot exceed 100 characters.';
        } elseif (strlen($username) < 3) {
            $error = 'Username must be at least 3 characters.';
        } elseif (strlen($username) > 30) {
            $error = 'Username must be 30 characters or less.';
        } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
            $error = 'Username can only contain letters, numbers, and underscores.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } elseif (strlen($password) < 8) {
            $error = 'Password must be at least 8 characters.';
        }

        return $error;
    }

    /**
     * Attempts to authenticate a user.
     *
     * @param string $email    The user's email address.
     * @param string $password The plaintext password.
     *
     * @return array{success: bool, user?: User, error?: string}
     */
    public function login(string $email, string $password): array {
        $result = ['success' => false];

        $email = trim(strtolower($email));
        $user = $this->user_mapper->findByEmail($email);

        if ($user === null) {
            $result['error'] = 'Invalid email or password.';
        } elseif ($user->is_disabled) {
            $result['error'] = 'This account has been disabled.';
        } elseif ($user->is_pending) {
            $result['error'] = 'Your account is awaiting admin approval.';
        } elseif (!$this->verifyPassword($password, $user->password_hash)) {
            $result['error'] = 'Invalid email or password.';
        } else {
            $result['success'] = true;
            $result['user'] = $user;
        }

        return $result;
    }

    /**
     * Hashes a password using Argon2id (preferred) or Bcrypt (fallback).
     *
     * @param string $password The plaintext password.
     *
     * @return string The hashed password.
     */
    protected function hashPassword(string $password): string {
        $algorithm = PASSWORD_ARGON2ID;

        // Fall back to bcrypt if Argon2id is not available
        if (!defined('PASSWORD_ARGON2ID')) {
            $algorithm = PASSWORD_BCRYPT;
        }

        return password_hash($password, $algorithm);
    }

    /**
     * Verifies a password against a hash.
     *
     * @param string $password The plaintext password.
     * @param string $hash     The stored hash.
     *
     * @return bool True if the password matches.
     */
    protected function verifyPassword(string $password, string $hash): bool {
        return password_verify($password, $hash);
    }

    /**
     * Checks if a password hash needs to be rehashed.
     *
     * @param string $hash The stored hash.
     *
     * @return bool True if rehashing is recommended.
     */
    public function needsRehash(string $hash): bool {
        $algorithm = PASSWORD_ARGON2ID;

        if (!defined('PASSWORD_ARGON2ID')) {
            $algorithm = PASSWORD_BCRYPT;
        }

        return password_needs_rehash($hash, $algorithm);
    }

    /**
     * Updates a user's password hash if needed.
     *
     * @param User   $user     The user to update.
     * @param string $password The plaintext password.
     *
     * @return void
     */
    public function rehashPasswordIfNeeded(User $user, string $password): void {
        if ($this->needsRehash($user->password_hash)) {
            $user->password_hash = $this->hashPassword($password);
            $this->user_mapper->save($user);
        }
    }
}
