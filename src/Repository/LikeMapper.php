<?php

declare(strict_types=1);

namespace Murmur\Repository;

use DealNews\DB\AbstractMapper;
use Murmur\Entity\Like;

/**
 * Data Mapper for the Like entity.
 *
 * Handles persistence operations for the `likes` table.
 */
class LikeMapper extends AbstractMapper {

    /**
     * Database configuration name.
     */
    public const DATABASE_NAME = 'murmur';

    /**
     * Database table name.
     */
    public const TABLE = 'likes';

    /**
     * Primary key column name.
     */
    public const PRIMARY_KEY = 'like_id';

    /**
     * Value object class for this mapper.
     */
    public const MAPPED_CLASS = Like::class;

    /**
     * Column mappings from database to entity properties.
     *
     * @var array<string, array>
     */
    public const MAPPING = [
        'like_id'    => [],
        'user_id'    => [],
        'post_id'    => [],
        'created_at' => ['read_only' => true],
    ];

    /**
     * Finds a like by user and post.
     *
     * @param int $user_id The user's ID.
     * @param int $post_id The post's ID.
     *
     * @return Like|null The like entity or null if not found.
     */
    public function findByUserAndPost(int $user_id, int $post_id): ?Like {
        $result = null;

        $likes = $this->find(['user_id' => $user_id, 'post_id' => $post_id], 1);

        if (!empty($likes)) {
            $result = reset($likes);
        }

        return $result;
    }

    /**
     * Counts likes for a post.
     *
     * @param int $post_id The post's ID.
     *
     * @return int The like count.
     */
    public function countByPostId(int $post_id): int {
        $sql = "SELECT COUNT(*) as count FROM {$this->table} WHERE post_id = :post_id";
        $rows = $this->crud->runFetch($sql, [':post_id' => $post_id]);

        return (int) ($rows[0]['count'] ?? 0);
    }

    /**
     * Gets like counts for multiple posts.
     *
     * @param array<int> $post_ids Array of post IDs.
     *
     * @return array<int, int> Map of post_id => like_count.
     */
    public function countByPostIds(array $post_ids): array {
        $result = [];

        if (empty($post_ids)) {
            return $result;
        }

        $params = [];
        $placeholders = [];
        foreach ($post_ids as $i => $post_id) {
            $key = ':post_id_' . $i;
            $placeholders[] = $key;
            $params[$key] = $post_id;
        }

        $placeholder_str = implode(',', $placeholders);
        $sql = "SELECT post_id, COUNT(*) as count FROM {$this->table} WHERE post_id IN ($placeholder_str) GROUP BY post_id";
        $rows = $this->crud->runFetch($sql, $params);

        foreach ($rows as $row) {
            $result[(int) $row['post_id']] = (int) $row['count'];
        }

        return $result;
    }

    /**
     * Gets which posts a user has liked from a list.
     *
     * @param int        $user_id  The user's ID.
     * @param array<int> $post_ids Array of post IDs.
     *
     * @return array<int> Array of post IDs the user has liked.
     */
    public function getUserLikedPostIds(int $user_id, array $post_ids): array {
        $result = [];

        if (empty($post_ids)) {
            return $result;
        }

        $params = [':user_id' => $user_id];
        $placeholders = [];
        foreach ($post_ids as $i => $post_id) {
            $key = ':post_id_' . $i;
            $placeholders[] = $key;
            $params[$key] = $post_id;
        }

        $placeholder_str = implode(',', $placeholders);
        $sql = "SELECT post_id FROM {$this->table} WHERE user_id = :user_id AND post_id IN ($placeholder_str)";
        $rows = $this->crud->runFetch($sql, $params);

        foreach ($rows as $row) {
            $result[] = (int) $row['post_id'];
        }

        return $result;
    }

    /**
     * Deletes a like.
     *
     * @param Like $like The like to delete.
     *
     * @return bool True on success.
     */
    public function deleteLike(Like $like): bool {
        $sql = "DELETE FROM {$this->table} WHERE like_id = :like_id";
        $this->crud->run($sql, [':like_id' => $like->like_id]);

        return true;
    }
}
