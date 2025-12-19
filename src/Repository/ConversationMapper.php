<?php

declare(strict_types=1);

namespace Murmur\Repository;

use DealNews\DB\AbstractMapper;
use Murmur\Entity\Conversation;

/**
 * Data Mapper for the Conversation entity.
 *
 * Handles persistence operations for the `conversations` table.
 * Provides methods for finding and managing message threads between users.
 */
class ConversationMapper extends AbstractMapper {

    /**
     * Database configuration name.
     */
    public const DATABASE_NAME = 'murmur';

    /**
     * Database table name.
     */
    public const TABLE = 'conversations';

    /**
     * Primary key column name.
     */
    public const PRIMARY_KEY = 'conversation_id';

    /**
     * Value object class for this mapper.
     */
    public const MAPPED_CLASS = Conversation::class;

    /**
     * Column mappings from database to entity properties.
     *
     * @var array<string, array<string, bool>>
     */
    public const MAPPING = [
        'conversation_id'  => [],
        'user_a_id'        => [],
        'user_b_id'        => [],
        'last_message_at'  => [],
        'created_at'       => ['read_only' => true],
    ];

    /**
     * Finds a conversation between two users.
     *
     * Normalizes user IDs so user_a_id < user_b_id for consistent lookups.
     *
     * @param int $user_id_1 First user ID.
     * @param int $user_id_2 Second user ID.
     *
     * @return Conversation|null The conversation or null if not found.
     */
    public function findByUsers(int $user_id_1, int $user_id_2): ?Conversation {
        $result = null;

        // Normalize order: user_a_id is always the smaller ID
        $user_a_id = min($user_id_1, $user_id_2);
        $user_b_id = max($user_id_1, $user_id_2);

        $conversations = $this->find([
            'user_a_id' => $user_a_id,
            'user_b_id' => $user_b_id,
        ], 1);

        if (!empty($conversations)) {
            $result = reset($conversations);
        }

        return $result;
    }

    /**
     * Retrieves all conversations for a user, ordered by most recent message.
     *
     * @param int $user_id The user ID.
     * @param int $limit   Maximum number of conversations to return.
     * @param int $offset  Number of conversations to skip.
     *
     * @return array<Conversation> Array of Conversation entities.
     */
    public function findByUserId(int $user_id, int $limit = 20, int $offset = 0): array {
        $result = [];

        $sql = "SELECT *
                FROM {$this->table}
                WHERE user_a_id = :user_id OR user_b_id = :user_id
                ORDER BY last_message_at DESC
                LIMIT :limit OFFSET :offset";

        $rows = $this->crud->runFetch($sql, [
            ':user_id' => $user_id,
            ':limit'   => $limit,
            ':offset'  => $offset,
        ]);

        foreach ($rows as $row) {
            $result[] = $this->buildObject($row);
        }

        return $result;
    }

    /**
     * Counts total conversations for a user.
     *
     * @param int $user_id The user ID.
     *
     * @return int The number of conversations.
     */
    public function countByUserId(int $user_id): int {
        $result = 0;

        $sql = "SELECT COUNT(*) as count
                FROM {$this->table}
                WHERE user_a_id = :user_id OR user_b_id = :user_id";

        $rows = $this->crud->runFetch($sql, [':user_id' => $user_id]);

        if (!empty($rows)) {
            $result = (int) $rows[0]['count'];
        }

        return $result;
    }

    /**
     * Updates the last_message_at timestamp for a conversation.
     *
     * @param int    $conversation_id The conversation ID.
     * @param string $timestamp       The timestamp string.
     *
     * @return void
     */
    public function updateLastMessageAt(int $conversation_id, string $timestamp): void {
        $sql = "UPDATE {$this->table}
                SET last_message_at = :timestamp
                WHERE conversation_id = :conversation_id";

        $this->crud->run($sql, [
            ':timestamp'       => $timestamp,
            ':conversation_id' => $conversation_id,
        ]);
    }

    /**
     * Creates a conversation between two users.
     *
     * Normalizes user IDs so user_a_id < user_b_id for consistent lookups.
     *
     * @param int $user_id_1 First user ID.
     * @param int $user_id_2 Second user ID.
     *
     * @return Conversation The created conversation.
     */
    public function createBetweenUsers(int $user_id_1, int $user_id_2): Conversation {
        // Normalize order: user_a_id is always the smaller ID
        $user_a_id = min($user_id_1, $user_id_2);
        $user_b_id = max($user_id_1, $user_id_2);

        $conversation = new Conversation();
        $conversation->user_a_id = $user_a_id;
        $conversation->user_b_id = $user_b_id;

        $this->save($conversation);

        return $conversation;
    }
}
