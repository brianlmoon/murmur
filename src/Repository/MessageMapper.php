<?php

declare(strict_types=1);

namespace Murmur\Repository;

use DealNews\DB\AbstractMapper;
use Murmur\Entity\Message;

/**
 * Data Mapper for the Message entity.
 *
 * Handles persistence operations for the `messages` table.
 * Provides methods for retrieving messages within conversations
 * and tracking read/deleted status.
 */
class MessageMapper extends AbstractMapper {

    /**
     * Database configuration name.
     */
    public const DATABASE_NAME = 'murmur';

    /**
     * Database table name.
     */
    public const TABLE = 'messages';

    /**
     * Primary key column name.
     */
    public const PRIMARY_KEY = 'message_id';

    /**
     * Value object class for this mapper.
     */
    public const MAPPED_CLASS = Message::class;

    /**
     * Column mappings from database to entity properties.
     *
     * @var array<string, array<string, bool>>
     */
    public const MAPPING = [
        'message_id'           => [],
        'conversation_id'      => [],
        'sender_id'            => [],
        'body'                 => [],
        'is_read'              => [],
        'deleted_by_sender'    => [],
        'deleted_by_recipient' => [],
        'created_at'           => ['read_only' => true],
    ];

    /**
     * Retrieves messages in a conversation for a specific user.
     *
     * Excludes messages that the user has deleted.
     *
     * @param int $conversation_id The conversation ID.
     * @param int $user_id         The viewing user's ID.
     * @param int $limit           Maximum messages to return.
     * @param int $offset          Number of messages to skip.
     *
     * @return array<Message> Array of Message entities, oldest first.
     */
    public function findByConversation(
        int $conversation_id,
        int $user_id,
        int $limit = 50,
        int $offset = 0
    ): array {
        $result = [];

        $sql = "SELECT *
                FROM {$this->table}
                WHERE conversation_id = :conversation_id
                  AND NOT (
                      (sender_id = :user_id AND deleted_by_sender = 1)
                      OR
                      (sender_id != :user_id AND deleted_by_recipient = 1)
                  )
                ORDER BY created_at ASC
                LIMIT :limit OFFSET :offset";

        $rows = $this->crud->runFetch($sql, [
            ':conversation_id' => $conversation_id,
            ':user_id'         => $user_id,
            ':limit'           => $limit,
            ':offset'          => $offset,
        ]);

        foreach ($rows as $row) {
            $result[] = $this->buildObject($row);
        }

        return $result;
    }

    /**
     * Counts unread messages for a user across all conversations.
     *
     * @param int $user_id The user ID.
     *
     * @return int The number of unread messages.
     */
    public function countUnreadForUser(int $user_id): int {
        $result = 0;

        $sql = "SELECT COUNT(*) as count
                FROM {$this->table} m
                INNER JOIN conversations c ON m.conversation_id = c.conversation_id
                WHERE (c.user_a_id = :user_id OR c.user_b_id = :user_id)
                  AND m.sender_id != :user_id
                  AND m.is_read = 0
                  AND m.deleted_by_recipient = 0";

        $rows = $this->crud->runFetch($sql, [':user_id' => $user_id]);

        if (!empty($rows)) {
            $result = (int) $rows[0]['count'];
        }

        return $result;
    }

    /**
     * Counts unread messages in a specific conversation for a user.
     *
     * @param int $conversation_id The conversation ID.
     * @param int $user_id         The user ID.
     *
     * @return int The number of unread messages.
     */
    public function countUnreadInConversation(int $conversation_id, int $user_id): int {
        $result = 0;

        $sql = "SELECT COUNT(*) as count
                FROM {$this->table}
                WHERE conversation_id = :conversation_id
                  AND sender_id != :user_id
                  AND is_read = 0
                  AND deleted_by_recipient = 0";

        $rows = $this->crud->runFetch($sql, [
            ':conversation_id' => $conversation_id,
            ':user_id'         => $user_id,
        ]);

        if (!empty($rows)) {
            $result = (int) $rows[0]['count'];
        }

        return $result;
    }

    /**
     * Marks all messages in a conversation as read for the recipient.
     *
     * @param int $conversation_id The conversation ID.
     * @param int $recipient_id    The recipient's user ID.
     *
     * @return void
     */
    public function markConversationRead(int $conversation_id, int $recipient_id): void {
        $sql = "UPDATE {$this->table}
                SET is_read = 1
                WHERE conversation_id = :conversation_id
                  AND sender_id != :recipient_id
                  AND is_read = 0";

        $this->crud->run($sql, [
            ':conversation_id' => $conversation_id,
            ':recipient_id'    => $recipient_id,
        ]);
    }

    /**
     * Soft-deletes a message for the sender.
     *
     * @param int $message_id The message ID.
     *
     * @return void
     */
    public function deleteForSender(int $message_id): void {
        $sql = "UPDATE {$this->table}
                SET deleted_by_sender = 1
                WHERE message_id = :message_id";

        $this->crud->run($sql, [':message_id' => $message_id]);
    }

    /**
     * Soft-deletes a message for the recipient.
     *
     * @param int $message_id The message ID.
     *
     * @return void
     */
    public function deleteForRecipient(int $message_id): void {
        $sql = "UPDATE {$this->table}
                SET deleted_by_recipient = 1
                WHERE message_id = :message_id";

        $this->crud->run($sql, [':message_id' => $message_id]);
    }

    /**
     * Soft-deletes all messages in a conversation for a specific user.
     *
     * @param int $conversation_id The conversation ID.
     * @param int $user_id         The user deleting the conversation.
     *
     * @return void
     */
    public function deleteConversationForUser(int $conversation_id, int $user_id): void {
        // Delete sent messages
        $sql_sender = "UPDATE {$this->table}
                       SET deleted_by_sender = 1
                       WHERE conversation_id = :conversation_id
                         AND sender_id = :user_id";

        $this->crud->run($sql_sender, [
            ':conversation_id' => $conversation_id,
            ':user_id'         => $user_id,
        ]);

        // Delete received messages
        $sql_recipient = "UPDATE {$this->table}
                          SET deleted_by_recipient = 1
                          WHERE conversation_id = :conversation_id
                            AND sender_id != :user_id";

        $this->crud->run($sql_recipient, [
            ':conversation_id' => $conversation_id,
            ':user_id'         => $user_id,
        ]);
    }

    /**
     * Gets the most recent message in a conversation for a user.
     *
     * @param int $conversation_id The conversation ID.
     * @param int $user_id         The viewing user's ID.
     *
     * @return Message|null The most recent message or null.
     */
    public function getLastMessage(int $conversation_id, int $user_id): ?Message {
        $result = null;

        $sql = "SELECT *
                FROM {$this->table}
                WHERE conversation_id = :conversation_id
                  AND NOT (
                      (sender_id = :user_id AND deleted_by_sender = 1)
                      OR
                      (sender_id != :user_id AND deleted_by_recipient = 1)
                  )
                ORDER BY created_at DESC
                LIMIT 1";

        $rows = $this->crud->runFetch($sql, [
            ':conversation_id' => $conversation_id,
            ':user_id'         => $user_id,
        ]);

        if (!empty($rows)) {
            $result = $this->buildObject($rows[0]);
        }

        return $result;
    }
}
