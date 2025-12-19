<?php

declare(strict_types=1);

namespace Murmur\Entity;

use Moonspot\ValueObjects\ValueObject;

/**
 * UserBlock entity representing a block relationship between users.
 *
 * Maps to the `user_blocks` table in the database.
 * Allows users to prevent others from messaging them.
 */
class UserBlock extends ValueObject {

    /**
     * Primary key identifier for the block relationship.
     */
    public ?int $block_id = null;

    /**
     * User ID of the person doing the blocking.
     */
    public int $blocker_id = 0;

    /**
     * User ID of the person being blocked.
     */
    public int $blocked_id = 0;

    /**
     * Timestamp when the block was created.
     */
    public ?string $created_at = null;
}
