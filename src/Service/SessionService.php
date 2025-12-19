<?php

declare(strict_types=1);

namespace Murmur\Service;

use Murmur\Entity\User;
use Murmur\Repository\UserMapper;

/**
 * Service for session management.
 *
 * Handles session lifecycle, user authentication state, and CSRF tokens.
 */
class SessionService {

    /**
     * Session key for the authenticated user ID.
     */
    protected const USER_ID_KEY = 'user_id';

    /**
     * Session key for CSRF tokens.
     */
    protected const CSRF_TOKEN_KEY = 'csrf_token';

    /**
     * Session key for flash messages.
     */
    protected const FLASH_KEY = 'flash_messages';

    /**
     * The user mapper for database operations.
     */
    protected UserMapper $user_mapper;

    /**
     * Whether the session has been started.
     */
    protected bool $session_started = false;

    /**
     * Creates a new SessionService instance.
     *
     * @param UserMapper $user_mapper The user mapper for database operations.
     */
    public function __construct(UserMapper $user_mapper) {
        $this->user_mapper = $user_mapper;
    }

    /**
     * Starts the session if not already started.
     *
     * @return void
     */
    public function start(): void {
        if (!$this->session_started && session_status() === PHP_SESSION_NONE) {
            session_start();
            $this->session_started = true;
        }
    }

    /**
     * Logs in a user by storing their ID in the session.
     *
     * @param User $user The user to log in.
     *
     * @return void
     */
    public function login(User $user): void {
        $this->start();
        $this->regenerateId();
        $_SESSION[self::USER_ID_KEY] = $user->user_id;
    }

    /**
     * Logs out the current user.
     *
     * @return void
     */
    public function logout(): void {
        $this->start();
        unset($_SESSION[self::USER_ID_KEY]);
        $this->regenerateId();
    }

    /**
     * Destroys the session entirely.
     *
     * @return void
     */
    public function destroy(): void {
        $this->start();

        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        session_destroy();
        $this->session_started = false;
    }

    /**
     * Regenerates the session ID to prevent fixation attacks.
     *
     * @return void
     */
    public function regenerateId(): void {
        $this->start();
        session_regenerate_id(true);
    }

    /**
     * Checks if a user is currently logged in.
     *
     * @return bool True if a user is logged in.
     */
    public function isLoggedIn(): bool {
        $this->start();

        return isset($_SESSION[self::USER_ID_KEY]);
    }

    /**
     * Gets the currently authenticated user.
     *
     * @return User|null The user or null if not logged in.
     */
    public function getCurrentUser(): ?User {
        $result = null;

        $this->start();

        if (isset($_SESSION[self::USER_ID_KEY])) {
            $result = $this->user_mapper->load($_SESSION[self::USER_ID_KEY]);
        }

        return $result;
    }

    /**
     * Gets the current user ID.
     *
     * @return int|null The user ID or null if not logged in.
     */
    public function getCurrentUserId(): ?int {
        $result = null;

        $this->start();

        if (isset($_SESSION[self::USER_ID_KEY])) {
            $result = (int) $_SESSION[self::USER_ID_KEY];
        }

        return $result;
    }

    /**
     * Generates a new CSRF token and stores it in the session.
     *
     * @return string The generated token.
     */
    public function generateCsrfToken(): string {
        $this->start();

        $token = bin2hex(random_bytes(32));
        $_SESSION[self::CSRF_TOKEN_KEY] = $token;

        return $token;
    }

    /**
     * Gets the current CSRF token, generating one if needed.
     *
     * @return string The CSRF token.
     */
    public function getCsrfToken(): string {
        $this->start();

        if (!isset($_SESSION[self::CSRF_TOKEN_KEY])) {
            return $this->generateCsrfToken();
        }

        return $_SESSION[self::CSRF_TOKEN_KEY];
    }

    /**
     * Validates a CSRF token against the session token.
     *
     * @param string $token The token to validate.
     *
     * @return bool True if the token is valid.
     */
    public function validateCsrfToken(string $token): bool {
        $this->start();

        $result = false;

        if (isset($_SESSION[self::CSRF_TOKEN_KEY])) {
            $result = hash_equals($_SESSION[self::CSRF_TOKEN_KEY], $token);
        }

        return $result;
    }

    /**
     * Adds a flash message to be displayed on the next request.
     *
     * @param string $type    The message type (success, error, info, warning).
     * @param string $message The message text.
     *
     * @return void
     */
    public function addFlash(string $type, string $message): void {
        $this->start();

        if (!isset($_SESSION[self::FLASH_KEY])) {
            $_SESSION[self::FLASH_KEY] = [];
        }

        $_SESSION[self::FLASH_KEY][] = [
            'type' => $type,
            'message' => $message,
        ];
    }

    /**
     * Gets and clears all flash messages.
     *
     * @return array<array{type: string, message: string}> The flash messages.
     */
    public function getFlashes(): array {
        $this->start();

        $result = [];

        if (isset($_SESSION[self::FLASH_KEY])) {
            $result = $_SESSION[self::FLASH_KEY];
            unset($_SESSION[self::FLASH_KEY]);
        }

        return $result;
    }
}
