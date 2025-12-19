<?php

declare(strict_types=1);

namespace Murmur\Entity;

use Moonspot\ValueObjects\ValueObject;

/**
 * Topic entity representing a conversation category.
 *
 * Maps to the `topics` table in the database.
 * Topics allow admins to categorize posts into conversation themes.
 */
class Topic extends ValueObject {

    /**
     * Primary key identifier for the topic.
     */
    public ?int $topic_id = null;

    /**
     * Topic name (unique, max 50 characters).
     */
    public string $name = '';

    /**
     * Timestamp when the topic was created.
     */
    public ?string $created_at = null;
}
