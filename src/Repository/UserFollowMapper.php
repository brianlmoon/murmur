<?php

declare(strict_types=1);

namespace Murmur\Repository;

use DealNews\DB\AbstractMapper;
use Murmur\Entity\UserFollow;

/**
 * Data Mapper for the UserFollow entity.
 *
 * Handles persistence operations for the `user_follows` table.
 * Provides methods for managing follow relationships between users.
 */
class UserFollowMapper extends AbstractMapper {

    /**
     * Database configuration name.
     */
    public const DATABASE_NAME = 'murmur';

    /**
     * Database table name.
     */
    public const TABLE = 'user_follows';

    /**
     * Primary key column name.
     */
    public const PRIMARY_KEY = 'follow_id';

    /**
     * Value object class for this mapper.
     */
    public const MAPPED_CLASS = UserFollow::class;

    /**
     * Column mappings from database to entity properties.
     *
     * @var array<string, array<string, bool>>
     */
    public const MAPPING = [
        'follow_id'    => [],
        'follower_id'  => [],
        'following_id' => [],
        'created_at'   => ['read_only' => true],
    ];

    /**
     * Finds a follow relationship between two users.
     *
     * @param int $follower_id  The ID of the user doing the following.
     * @param int $following_id The ID of the user being followed.
     *
     * @return UserFollow|null The follow relationship or null if not found.
     */
    public function findByFollowerAndFollowing(int $follower_id, int $following_id): ?UserFollow {
        $result = null;

        $follows = $this->find([
            'follower_id'  => $follower_id,
            'following_id' => $following_id,
        ], 1);

        if (!empty($follows)) {
            $result = reset($follows);
        }

        return $result;
    }

    /**
     * Retrieves all follow relationships for a given follower.
     *
     * @param int $follower_id The ID of the user doing the following.
     *
     * @return array<UserFollow> Array of UserFollow entities.
     */
    public function findByFollowerId(int $follower_id): array {
        return $this->find(['follower_id' => $follower_id]) ?? [];
    }

    /**
     * Retrieves all follow relationships for a given user being followed.
     *
     * @param int $following_id The ID of the user being followed.
     *
     * @return array<UserFollow> Array of UserFollow entities.
     */
    public function findByFollowingId(int $following_id): array {
        return $this->find(['following_id' => $following_id]) ?? [];
    }

    /**
     * Retrieves just the user IDs that a user is following.
     *
     * @param int $follower_id The ID of the user doing the following.
     *
     * @return array<int> Array of user IDs being followed.
     */
    public function getFollowingIds(int $follower_id): array {
        $result = [];

        $sql = "SELECT following_id FROM {$this->table} WHERE follower_id = :follower_id";
        $rows = $this->crud->runFetch($sql, [':follower_id' => $follower_id]);

        foreach ($rows as $row) {
            $result[] = (int) $row['following_id'];
        }

        return $result;
    }

    /**
     * Retrieves just the user IDs that follow a given user.
     *
     * @param int $following_id The ID of the user being followed.
     *
     * @return array<int> Array of follower user IDs.
     */
    public function getFollowerIds(int $following_id): array {
        $result = [];

        $sql = "SELECT follower_id FROM {$this->table} WHERE following_id = :following_id";
        $rows = $this->crud->runFetch($sql, [':following_id' => $following_id]);

        foreach ($rows as $row) {
            $result[] = (int) $row['follower_id'];
        }

        return $result;
    }

    /**
     * Counts the number of followers for a user.
     *
     * @param int $user_id The ID of the user to count followers for.
     *
     * @return int The number of followers.
     */
    public function countFollowers(int $user_id): int {
        $result = 0;

        $sql = "SELECT COUNT(*) as count FROM {$this->table} WHERE following_id = :user_id";
        $rows = $this->crud->runFetch($sql, [':user_id' => $user_id]);

        if (!empty($rows)) {
            $result = (int) $rows[0]['count'];
        }

        return $result;
    }

    /**
     * Counts the number of users that a user is following.
     *
     * @param int $user_id The ID of the user to count following for.
     *
     * @return int The number of users being followed.
     */
    public function countFollowing(int $user_id): int {
        $result = 0;

        $sql = "SELECT COUNT(*) as count FROM {$this->table} WHERE follower_id = :user_id";
        $rows = $this->crud->runFetch($sql, [':user_id' => $user_id]);

        if (!empty($rows)) {
            $result = (int) $rows[0]['count'];
        }

        return $result;
    }

    /**
     * Checks if two users are mutual follows (both follow each other).
     *
     * @param int $user_id_a First user ID.
     * @param int $user_id_b Second user ID.
     *
     * @return bool True if both users follow each other.
     */
    public function areMutualFollows(int $user_id_a, int $user_id_b): bool {
        $result = false;

        $sql = "SELECT COUNT(*) as count
                FROM {$this->table} f1
                INNER JOIN {$this->table} f2 ON
                    f1.follower_id = f2.following_id AND
                    f1.following_id = f2.follower_id
                WHERE
                    f1.follower_id = :user_a AND
                    f1.following_id = :user_b";

        $rows = $this->crud->runFetch($sql, [
            ':user_a' => $user_id_a,
            ':user_b' => $user_id_b,
        ]);

        if (!empty($rows) && (int) $rows[0]['count'] > 0) {
            $result = true;
        }

        return $result;
    }
}
