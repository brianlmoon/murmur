<?php

declare(strict_types=1);

namespace Murmur\Repository;

use DealNews\DB\AbstractMapper;
use Murmur\Entity\UserBlock;

/**
 * Data Mapper for the UserBlock entity.
 *
 * Handles persistence operations for the `user_blocks` table.
 * Provides methods for managing block relationships between users.
 */
class UserBlockMapper extends AbstractMapper {

    /**
     * Database configuration name.
     */
    public const DATABASE_NAME = 'murmur';

    /**
     * Database table name.
     */
    public const TABLE = 'user_blocks';

    /**
     * Primary key column name.
     */
    public const PRIMARY_KEY = 'block_id';

    /**
     * Value object class for this mapper.
     */
    public const MAPPED_CLASS = UserBlock::class;

    /**
     * Column mappings from database to entity properties.
     *
     * @var array<string, array<string, bool>>
     */
    public const MAPPING = [
        'block_id'   => [],
        'blocker_id' => [],
        'blocked_id' => [],
        'created_at' => ['read_only' => true],
    ];

    /**
     * Finds a block relationship between two users.
     *
     * @param int $blocker_id The ID of the user who blocked.
     * @param int $blocked_id The ID of the user who was blocked.
     *
     * @return UserBlock|null The block relationship or null if not found.
     */
    public function findByUsers(int $blocker_id, int $blocked_id): ?UserBlock {
        $result = null;

        $blocks = $this->find([
            'blocker_id' => $blocker_id,
            'blocked_id' => $blocked_id,
        ], 1);

        if (!empty($blocks)) {
            $result = reset($blocks);
        }

        return $result;
    }

    /**
     * Checks if either user has blocked the other.
     *
     * @param int $user_id_a First user ID.
     * @param int $user_id_b Second user ID.
     *
     * @return bool True if either user has blocked the other.
     */
    public function hasBlockBetween(int $user_id_a, int $user_id_b): bool {
        $result = false;

        $sql = "SELECT COUNT(*) as count
                FROM {$this->table}
                WHERE (blocker_id = :user_a AND blocked_id = :user_b)
                   OR (blocker_id = :user_b AND blocked_id = :user_a)";

        $rows = $this->crud->runFetch($sql, [
            ':user_a' => $user_id_a,
            ':user_b' => $user_id_b,
        ]);

        if (!empty($rows) && (int) $rows[0]['count'] > 0) {
            $result = true;
        }

        return $result;
    }

    /**
     * Retrieves all users that a user has blocked.
     *
     * @param int $blocker_id The user ID of the blocker.
     *
     * @return array<int> Array of blocked user IDs.
     */
    public function getBlockedIds(int $blocker_id): array {
        $result = [];

        $sql = "SELECT blocked_id FROM {$this->table} WHERE blocker_id = :blocker_id";
        $rows = $this->crud->runFetch($sql, [':blocker_id' => $blocker_id]);

        foreach ($rows as $row) {
            $result[] = (int) $row['blocked_id'];
        }

        return $result;
    }

    /**
     * Retrieves all users who have blocked a specific user.
     *
     * @param int $blocked_id The user ID who has been blocked.
     *
     * @return array<int> Array of blocker user IDs.
     */
    public function getBlockerIds(int $blocked_id): array {
        $result = [];

        $sql = "SELECT blocker_id FROM {$this->table} WHERE blocked_id = :blocked_id";
        $rows = $this->crud->runFetch($sql, [':blocked_id' => $blocked_id]);

        foreach ($rows as $row) {
            $result[] = (int) $row['blocker_id'];
        }

        return $result;
    }
}
