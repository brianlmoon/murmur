<?php

declare(strict_types=1);

namespace Murmur\Handler;

use Murmur\Repository\SessionMapper;
use SessionHandlerInterface;

/**
 * Database-backed session handler implementing PHP's SessionHandlerInterface.
 *
 * This handler stores session data in the database, enabling session sharing
 * across multiple application instances behind a load balancer.
 */
class DatabaseSessionHandler implements SessionHandlerInterface {

    /**
     * The session mapper for database operations.
     */
    protected SessionMapper $session_mapper;

    /**
     * Maximum session lifetime in seconds.
     */
    protected int $max_lifetime;

    /**
     * Creates a new DatabaseSessionHandler instance.
     *
     * @param SessionMapper $session_mapper The session mapper for database operations.
     * @param int           $max_lifetime   Session lifetime in seconds (default: 604800 = 7 days).
     */
    public function __construct(SessionMapper $session_mapper, int $max_lifetime = 604800) {
        $this->session_mapper = $session_mapper;
        $this->max_lifetime   = $max_lifetime;
    }

    /**
     * Opens the session (required by interface but not used).
     *
     * @param string $path The session save path.
     * @param string $name The session name.
     *
     * @return bool Always returns true.
     */
    public function open(string $path, string $name): bool {
        return true;
    }

    /**
     * Closes the session (required by interface but not used).
     *
     * @return bool Always returns true.
     */
    public function close(): bool {
        return true;
    }

    /**
     * Reads session data from the database.
     *
     * Note: We do not update last_active here because write() is called
     * at the end of every request, which handles the timestamp update.
     *
     * @param string $id The session ID.
     *
     * @return string|false The session data or empty string if not found.
     */
    public function read(string $id): string|false {
        $result = '';

        try {
            $session = $this->session_mapper->findBySessionId($id);

            if ($session !== null) {
                $result = $session->data;
            }
        } catch (\Throwable $e) {
            error_log("Session read error: " . $e->getMessage());
            $result = false;
        }

        return $result;
    }

    /**
     * Writes session data to the database.
     *
     * Note: Uses read-then-write pattern. For high-concurrency deployments,
     * consider database-native UPSERT (ON DUPLICATE KEY UPDATE / ON CONFLICT).
     * See Section 9 "Performance Considerations" in DB_SESSIONS.md for details.
     *
     * @param string $id   The session ID.
     * @param string $data The serialized session data.
     *
     * @return bool True on success.
     */
    public function write(string $id, string $data): bool {
        $result = false;

        try {
            $existing = $this->session_mapper->findBySessionId($id);
            $now      = time();

            if ($existing === null) {
                $this->session_mapper->createSession($id, $data, $now);
            } else {
                $this->session_mapper->updateSession($id, $data, $now);
            }

            $result = true;
        } catch (\Throwable $e) {
            error_log("Session write error: " . $e->getMessage());
        }

        return $result;
    }

    /**
     * Destroys a session by removing it from the database.
     *
     * @param string $id The session ID.
     *
     * @return bool True on success.
     */
    public function destroy(string $id): bool {
        $result = false;

        try {
            $this->session_mapper->delete($id);
            $result = true;
        } catch (\Throwable $e) {
            error_log("Session destroy error: " . $e->getMessage());
        }

        return $result;
    }

    /**
     * Garbage collection - removes expired sessions.
     *
     * @param int $max_lifetime Maximum session lifetime in seconds.
     *
     * @return int|false Number of deleted sessions.
     */
    public function gc(int $max_lifetime): int|false {
        $result = false;

        try {
            $result = $this->session_mapper->deleteExpired($max_lifetime);
        } catch (\Throwable $e) {
            error_log("Session GC error: " . $e->getMessage());
        }

        return $result;
    }
}
