<?php

declare(strict_types=1);

namespace Murmur\Entity;

use Moonspot\ValueObjects\ValueObject;

/**
 * Conversation entity representing a message thread between two users.
 *
 * Maps to the `conversations` table in the database.
 * Groups messages between two users for efficient inbox queries.
 *
 * Convention: user_a_id is always less than user_b_id to ensure
 * consistent lookups regardless of which user initiates.
 */
class Conversation extends ValueObject {

    /**
     * Primary key identifier for the conversation.
     */
    public ?int $conversation_id = null;

    /**
     * First participant's user ID (always the lower ID).
     */
    public int $user_a_id = 0;

    /**
     * Second participant's user ID (always the higher ID).
     */
    public int $user_b_id = 0;

    /**
     * Timestamp of the most recent message for inbox sorting.
     */
    public ?string $last_message_at = null;

    /**
     * Timestamp when the conversation was created.
     */
    public ?string $created_at = null;
}
