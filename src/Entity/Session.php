<?php

declare(strict_types=1);

namespace Murmur\Entity;

use Moonspot\ValueObjects\ValueObject;

/**
 * Session entity representing a user session in the database.
 *
 * Used by the DatabaseSessionHandler to persist session data across
 * multiple application instances.
 */
class Session extends ValueObject {

    /**
     * The unique session identifier (PHP session ID).
     */
    public string $session_id = '';

    /**
     * The user ID if logged in, null otherwise.
     */
    public ?int $user_id = null;

    /**
     * Serialized session data.
     */
    public string $data = '';

    /**
     * Unix timestamp of last activity.
     */
    public int $last_active = 0;

    /**
     * Timestamp when session was created.
     */
    public string $created_at = '';
}
