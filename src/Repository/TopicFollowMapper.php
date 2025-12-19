<?php

declare(strict_types=1);

namespace Murmur\Repository;

use DealNews\DB\AbstractMapper;
use Murmur\Entity\TopicFollow;

/**
 * Data Mapper for the TopicFollow entity.
 *
 * Handles persistence operations for the `topic_follows` table.
 */
class TopicFollowMapper extends AbstractMapper {

    /**
     * Database configuration name.
     */
    public const DATABASE_NAME = 'murmur';

    /**
     * Database table name.
     */
    public const TABLE = 'topic_follows';

    /**
     * Primary key column name.
     */
    public const PRIMARY_KEY = 'follow_id';

    /**
     * Value object class for this mapper.
     */
    public const MAPPED_CLASS = TopicFollow::class;

    /**
     * Column mappings from database to entity properties.
     *
     * @var array<string, array>
     */
    public const MAPPING = [
        'follow_id'  => [],
        'user_id'    => [],
        'topic_id'   => [],
        'created_at' => ['read_only' => true],
    ];

    /**
     * Finds a follow relationship between a user and topic.
     *
     * @param int $user_id  The user ID.
     * @param int $topic_id The topic ID.
     *
     * @return TopicFollow|null The follow relationship or null if not found.
     */
    public function findByUserAndTopic(int $user_id, int $topic_id): ?TopicFollow {
        $result = null;

        $follows = $this->find(['user_id' => $user_id, 'topic_id' => $topic_id], 1);

        if (!empty($follows)) {
            $result = $follows[0];
        }

        return $result;
    }

    /**
     * Retrieves all topic follows for a user.
     *
     * @param int $user_id The user ID.
     *
     * @return array<TopicFollow> Array of TopicFollow entities.
     */
    public function findByUserId(int $user_id): array {
        return $this->find(['user_id' => $user_id]) ?? [];
    }

    /**
     * Retrieves just the topic IDs that a user follows.
     *
     * @param int $user_id The user ID.
     *
     * @return array<int> Array of topic IDs.
     */
    public function getFollowedTopicIds(int $user_id): array {
        $result = [];

        $sql = "SELECT topic_id FROM {$this->table} WHERE user_id = :user_id";
        $rows = $this->crud->runFetch($sql, [':user_id' => $user_id]);

        foreach ($rows as $row) {
            $result[] = (int) $row['topic_id'];
        }

        return $result;
    }

    /**
     * Counts followers for a topic.
     *
     * @param int $topic_id The topic ID.
     *
     * @return int The number of followers.
     */
    public function countByTopicId(int $topic_id): int {
        $result = 0;

        $sql = "SELECT COUNT(*) as count FROM {$this->table} WHERE topic_id = :topic_id";
        $rows = $this->crud->runFetch($sql, [':topic_id' => $topic_id]);

        if (!empty($rows)) {
            $result = (int) $rows[0]['count'];
        }

        return $result;
    }
}
