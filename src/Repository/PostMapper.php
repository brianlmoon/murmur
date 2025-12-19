<?php

declare(strict_types=1);

namespace Murmur\Repository;

use DealNews\DB\AbstractMapper;
use Murmur\Entity\Post;

/**
 * Data Mapper for the Post entity.
 *
 * Handles persistence operations for the `posts` table.
 */
class PostMapper extends AbstractMapper {

    /**
     * Database configuration name.
     */
    public const DATABASE_NAME = 'murmur';

    /**
     * Database table name.
     */
    public const TABLE = 'posts';

    /**
     * Primary key column name.
     */
    public const PRIMARY_KEY = 'post_id';

    /**
     * Value object class for this mapper.
     */
    public const MAPPED_CLASS = Post::class;

    /**
     * Column mappings from database to entity properties.
     *
     * @var array<string, array>
     */
    public const MAPPING = [
        'post_id'    => [],
        'user_id'    => [],
        'parent_id'  => [],
        'topic_id'   => [],
        'body'       => [],
        'image_path' => [],
        'created_at' => ['read_only' => true],
        'updated_at' => ['read_only' => true],
    ];

    /**
     * Retrieves all top-level posts (not replies) in chronological order.
     *
     * @param int $limit  Maximum number of posts to return.
     * @param int $offset Number of posts to skip.
     *
     * @return array<Post> Array of Post entities.
     */
    public function findFeed(int $limit = 50, int $offset = 0): array {
        $result = [];

        $sql = "SELECT * FROM {$this->table} WHERE parent_id IS NULL ORDER BY created_at DESC LIMIT :limit OFFSET :offset";
        $rows = $this->crud->runFetch($sql, [':limit' => $limit, ':offset' => $offset]);

        foreach ($rows as $row) {
            $result[] = $this->setData($row);
        }

        return $result;
    }

    /**
     * Retrieves all posts by a specific user.
     *
     * @param int $user_id The user's ID.
     * @param int $limit   Maximum number of posts to return.
     * @param int $offset  Number of posts to skip.
     *
     * @return array<Post> Array of Post entities.
     */
    public function findByUserId(int $user_id, int $limit = 50, int $offset = 0): array {
        return $this->find(
            ['user_id' => $user_id],
            $limit,
            $offset,
            'created_at DESC'
        ) ?? [];
    }

    /**
     * Retrieves all replies to a specific post.
     *
     * @param int    $parent_id The parent post's ID.
     * @param int    $limit     Maximum number of replies to return.
     * @param int    $offset    Number of replies to skip.
     * @param string $order     Sort order: 'ASC' (oldest first) or 'DESC' (newest first).
     *
     * @return array<Post> Array of Post entities.
     */
    public function findReplies(int $parent_id, int $limit = 50, int $offset = 0, string $order = 'ASC'): array {
        $order = strtoupper($order) === 'DESC' ? 'DESC' : 'ASC';

        return $this->find(
            ['parent_id' => $parent_id],
            $limit,
            $offset,
            'created_at ' . $order
        ) ?? [];
    }

    /**
     * Counts replies for multiple posts.
     *
     * @param array<int> $post_ids Array of post IDs.
     *
     * @return array<int, int> Associative array of post_id => reply_count.
     */
    public function countRepliesByPostIds(array $post_ids): array {
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
        $sql = "SELECT parent_id, COUNT(*) as reply_count FROM {$this->table} WHERE parent_id IN ({$placeholder_str}) GROUP BY parent_id";
        $rows = $this->crud->runFetch($sql, $params);

        foreach ($rows as $row) {
            $result[(int) $row['parent_id']] = (int) $row['reply_count'];
        }

        return $result;
    }

    /**
     * Retrieves feed posts filtered by topic IDs.
     *
     * @param array<int> $topic_ids Array of topic IDs to filter by.
     * @param int        $limit     Maximum number of posts to return.
     * @param int        $offset    Number of posts to skip.
     *
     * @return array<Post> Array of Post entities.
     */
    public function findFeedByTopics(array $topic_ids, int $limit = 50, int $offset = 0): array {
        $result = [];

        if (empty($topic_ids)) {
            return $result;
        }

        $params = [':limit' => $limit, ':offset' => $offset];
        $placeholders = [];
        foreach ($topic_ids as $i => $topic_id) {
            $key = ':topic_id_' . $i;
            $placeholders[] = $key;
            $params[$key] = $topic_id;
        }

        $placeholder_str = implode(',', $placeholders);
        $sql = "SELECT * FROM {$this->table} WHERE parent_id IS NULL AND topic_id IN ({$placeholder_str}) ORDER BY created_at DESC LIMIT :limit OFFSET :offset";
        $rows = $this->crud->runFetch($sql, $params);

        foreach ($rows as $row) {
            $result[] = $this->setData($row);
        }

        return $result;
    }
}
