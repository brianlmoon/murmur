<?php

declare(strict_types=1);

namespace Murmur\Repository;

use DealNews\DB\AbstractMapper;
use Murmur\Entity\Topic;

/**
 * Data Mapper for the Topic entity.
 *
 * Handles persistence operations for the `topics` table.
 */
class TopicMapper extends AbstractMapper {

    /**
     * Database configuration name.
     */
    public const DATABASE_NAME = 'murmur';

    /**
     * Database table name.
     */
    public const TABLE = 'topics';

    /**
     * Primary key column name.
     */
    public const PRIMARY_KEY = 'topic_id';

    /**
     * Value object class for this mapper.
     */
    public const MAPPED_CLASS = Topic::class;

    /**
     * Column mappings from database to entity properties.
     *
     * @var array<string, array>
     */
    public const MAPPING = [
        'topic_id'   => [],
        'name'       => [],
        'created_at' => ['read_only' => true],
    ];

    /**
     * Retrieves all topics ordered by name.
     *
     * @return array<Topic> Array of Topic entities.
     */
    public function findAll(): array {
        return $this->find([], null, 0, 'name ASC') ?? [];
    }

    /**
     * Finds a topic by name.
     *
     * @param string $name The topic name to find.
     *
     * @return Topic|null The topic or null if not found.
     */
    public function findByName(string $name): ?Topic {
        $result = null;

        $topics = $this->find(['name' => $name], 1);

        if (!empty($topics)) {
            $result = $topics[0];
        }

        return $result;
    }
}
