<?php

declare(strict_types=1);

namespace Murmur\Repository;

use DealNews\DB\AbstractMapper;
use Murmur\Entity\User;

/**
 * Data Mapper for the User entity.
 *
 * Handles persistence operations for the `users` table.
 */
class UserMapper extends AbstractMapper {

    /**
     * Database configuration name.
     */
    public const DATABASE_NAME = 'murmur';

    /**
     * Database table name.
     */
    public const TABLE = 'users';

    /**
     * Primary key column name.
     */
    public const PRIMARY_KEY = 'user_id';

    /**
     * Value object class for this mapper.
     */
    public const MAPPED_CLASS = User::class;

    /**
     * Column mappings from database to entity properties.
     *
     * @var array<string, array>
     */
    public const MAPPING = [
        'user_id'       => [],
        'username'      => [],
        'name'          => [],
        'email'         => [],
        'password_hash' => [],
        'bio'           => [],
        'avatar_path'   => [],
        'is_admin'      => [],
        'is_disabled'   => [],
        'is_pending'    => [],
        'created_at'    => ['read_only' => true],
        'updated_at'    => ['read_only' => true],
    ];

    /**
     * Finds a user by their username.
     *
     * @param string $username The username to search for.
     *
     * @return User|null The user entity or null if not found.
     */
    public function findByUsername(string $username): ?User {
        $result = null;

        $users = $this->find(['username' => $username], 1);

        if (!empty($users)) {
            $result = reset($users);
        }

        return $result;
    }

    /**
     * Finds a user by their email address.
     *
     * @param string $email The email to search for.
     *
     * @return User|null The user entity or null if not found.
     */
    public function findByEmail(string $email): ?User {
        $result = null;

        $users = $this->find(['email' => $email], 1);

        if (!empty($users)) {
            $result = reset($users);
        }

        return $result;
    }

    /**
     * Counts the total number of users in the database.
     *
     * @return int The total user count.
     */
    public function countAll(): int {
        $sql = "SELECT COUNT(*) as count FROM {$this->table}";
        $rows = $this->crud->runFetch($sql);

        return (int) ($rows[0]['count'] ?? 0);
    }

    /**
     * Finds all users pending admin approval.
     *
     * @return array<User> Array of pending users.
     */
    public function findPendingUsers(): array {
        $result = [];

        $users = $this->find(['is_pending' => true]);

        if (!empty($users)) {
            $result = array_values($users);
        }

        return $result;
    }

    /**
     * Counts users pending admin approval.
     *
     * @return int The pending user count.
     */
    public function countPending(): int {
        $sql = "SELECT COUNT(*) as count FROM {$this->table} WHERE is_pending = 1";
        $rows = $this->crud->runFetch($sql);

        return (int) ($rows[0]['count'] ?? 0);
    }

    /**
     * Searches for users by username or email.
     *
     * @param string $query  The search query.
     * @param int    $limit  Maximum users to return.
     * @param int    $offset Number of users to skip.
     *
     * @return array<User> Array of matching users.
     */
    public function searchUsers(string $query, int $limit = 50, int $offset = 0): array {
        $result = [];

        $search_term = '%' . $query . '%';

        $sql = "SELECT * FROM {$this->table} 
                WHERE username LIKE :search OR email LIKE :search 
                ORDER BY 
                    CASE 
                        WHEN username = :exact THEN 1
                        WHEN email = :exact THEN 2
                        WHEN username LIKE :starts THEN 3
                        WHEN email LIKE :starts THEN 4
                        ELSE 5
                    END,
                    created_at DESC
                LIMIT :limit OFFSET :offset";

        $rows = $this->crud->runFetch($sql, [
            ':search' => $search_term,
            ':exact' => $query,
            ':starts' => $query . '%',
            ':limit' => $limit,
            ':offset' => $offset,
        ]);

        foreach ($rows as $row) {
            $result[] = $this->setData($row);
        }

        return $result;
    }
}
