<?php

declare(strict_types=1);

namespace Murmur\Entity;

use Moonspot\ValueObjects\ValueObject;

/**
 * UserFollow entity representing a follow relationship between users.
 *
 * Maps to the `user_follows` table in the database.
 * Tracks which users are following which other users.
 */
class UserFollow extends ValueObject {

    /**
     * Primary key identifier for the follow relationship.
     */
    public ?int $follow_id = null;

    /**
     * User ID of the person doing the following.
     */
    public int $follower_id = 0;

    /**
     * User ID of the person being followed.
     */
    public int $following_id = 0;

    /**
     * Timestamp when the follow was created.
     */
    public ?string $created_at = null;
}
