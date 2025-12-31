<?php

declare(strict_types=1);

namespace Murmur\Repository;

use DealNews\DB\AbstractMapper;
use Murmur\Entity\Session;

/**
 * Data Mapper for the Session entity.
 *
 * Handles persistence operations for the `sessions` table.
 *
 * Note: This mapper uses a string primary key. Do not use the inherited
 * save() method; use direct CRUD operations instead.
 */
class SessionMapper extends AbstractMapper {

    /**
     * Database configuration name.
     */
    public const DATABASE_NAME = 'murmur';

    /**
     * Database table name.
     */
    public const TABLE = 'sessions';

    /**
     * Primary key column name.
     */
    public const PRIMARY_KEY = 'session_id';

    /**
     * Value object class for this mapper.
     */
    public const MAPPED_CLASS = Session::class;

    /**
     * Column mappings from database to entity properties.
     *
     * @var array<string, array<string, mixed>>
     */
    public const MAPPING = [
        'session_id'  => [],
        'user_id'     => [],
        'data'        => [],
        'last_active' => [],
        'created_at'  => ['read_only' => true],
    ];

    /**
     * Finds a session by session ID.
     *
     * @param string $session_id The session ID.
     *
     * @return Session|null The session or null if not found.
     */
    public function findBySessionId(string $session_id): ?Session {
        return $this->load($session_id);
    }

    /**
     * Creates a new session record.
     *
     * @param string $session_id  The session ID.
     * @param string $data        The session data.
     * @param int    $last_active The last active timestamp.
     *
     * @return bool True on success.
     */
    public function createSession(string $session_id, string $data, int $last_active): bool {
        $this->crud->create(self::TABLE, [
            'session_id'  => $session_id,
            'data'        => $data,
            'last_active' => $last_active,
        ]);

        return true;
    }

    /**
     * Updates an existing session record.
     *
     * @param string $session_id  The session ID.
     * @param string $data        The session data.
     * @param int    $last_active The last active timestamp.
     *
     * @return bool True on success.
     */
    public function updateSession(string $session_id, string $data, int $last_active): bool {
        $this->crud->update(
            self::TABLE,
            [
                'data'        => $data,
                'last_active' => $last_active,
            ],
            ['session_id' => $session_id]
        );

        return true;
    }

    /**
     * Deletes sessions older than the given timestamp.
     *
     * @param int $max_lifetime Maximum session lifetime in seconds.
     *
     * @return int Number of deleted sessions.
     */
    public function deleteExpired(int $max_lifetime): int {
        $cutoff = time() - $max_lifetime;
        $sql    = "DELETE FROM " . self::TABLE . " WHERE last_active < :cutoff";
        $stmt   = $this->crud->run($sql, ['cutoff' => $cutoff]);

        return $stmt->rowCount();
    }

    /**
     * Deletes all sessions for a user except the specified session.
     *
     * Used for "log out all other devices" functionality.
     *
     * @param int    $user_id           The user ID.
     * @param string $except_session_id The session ID to keep.
     *
     * @return int Number of deleted sessions.
     */
    public function deleteByUserIdExcept(int $user_id, string $except_session_id): int {
        $sql  = "DELETE FROM " . self::TABLE . "
                WHERE user_id = :user_id
                AND session_id != :session_id";
        $stmt = $this->crud->run($sql, [
            'user_id'    => $user_id,
            'session_id' => $except_session_id,
        ]);

        return $stmt->rowCount();
    }

    /**
     * Deletes all sessions for a specific user.
     *
     * Used for admin "force logout" or account deletion.
     *
     * @param int $user_id The user ID.
     *
     * @return int Number of deleted sessions.
     */
    public function deleteByUserId(int $user_id): int {
        $sql  = "DELETE FROM " . self::TABLE . " WHERE user_id = :user_id";
        $stmt = $this->crud->run($sql, ['user_id' => $user_id]);

        return $stmt->rowCount();
    }

    /**
     * Updates the user_id for a session.
     *
     * Called when a user logs in.
     *
     * @param string $session_id The session ID.
     * @param int    $user_id    The user ID.
     *
     * @return bool True on success.
     */
    public function updateUserId(string $session_id, int $user_id): bool {
        $sql  = "UPDATE " . self::TABLE . " SET user_id = :user_id WHERE session_id = :session_id";
        $stmt = $this->crud->run($sql, [
            'user_id'    => $user_id,
            'session_id' => $session_id,
        ]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Clears the user_id for a session.
     *
     * Called when a user logs out.
     *
     * @param string $session_id The session ID.
     *
     * @return bool True on success.
     */
    public function clearUserId(string $session_id): bool {
        $sql  = "UPDATE " . self::TABLE . " SET user_id = NULL WHERE session_id = :session_id";
        $stmt = $this->crud->run($sql, ['session_id' => $session_id]);

        return $stmt->rowCount() > 0;
    }
}
