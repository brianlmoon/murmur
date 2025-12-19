<?php

declare(strict_types=1);

namespace Murmur\Entity;

use Moonspot\ValueObjects\ValueObject;

/**
 * TopicFollow entity representing a user's subscription to a topic.
 *
 * Maps to the `topic_follows` table in the database.
 * Allows users to follow topics and filter their feed.
 */
class TopicFollow extends ValueObject {

    /**
     * Primary key identifier for the follow relationship.
     */
    public ?int $follow_id = null;

    /**
     * User ID who is following the topic.
     */
    public int $user_id = 0;

    /**
     * Topic ID being followed.
     */
    public int $topic_id = 0;

    /**
     * Timestamp when the follow was created.
     */
    public ?string $created_at = null;
}
