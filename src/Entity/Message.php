<?php

declare(strict_types=1);

namespace Murmur\Entity;

use Moonspot\ValueObjects\ValueObject;

/**
 * Message entity representing a single message within a conversation.
 *
 * Maps to the `messages` table in the database.
 * Supports soft deletes for both sender and recipient independently.
 */
class Message extends ValueObject {

    /**
     * Primary key identifier for the message.
     */
    public ?int $message_id = null;

    /**
     * Foreign key to the parent conversation.
     */
    public int $conversation_id = 0;

    /**
     * Foreign key to the user who sent the message.
     */
    public int $sender_id = 0;

    /**
     * Text content of the message (500 character limit enforced by service).
     */
    public string $body = '';

    /**
     * Whether the recipient has read this message.
     */
    public bool $is_read = false;

    /**
     * Whether the sender has deleted this message from their view.
     */
    public bool $deleted_by_sender = false;

    /**
     * Whether the recipient has deleted this message from their view.
     */
    public bool $deleted_by_recipient = false;

    /**
     * Timestamp when the message was sent.
     */
    public ?string $created_at = null;
}
