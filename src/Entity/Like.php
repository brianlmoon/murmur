<?php

declare(strict_types=1);

namespace Murmur\Entity;

use Moonspot\ValueObjects\ValueObject;

/**
 * Like entity representing a user's like on a post.
 *
 * Maps to the `likes` table in the database.
 */
class Like extends ValueObject {

    /**
     * Primary key identifier for the like.
     */
    public ?int $like_id = null;

    /**
     * Foreign key to the user who created this like.
     */
    public int $user_id = 0;

    /**
     * Foreign key to the post that was liked.
     */
    public int $post_id = 0;

    /**
     * Timestamp when the like was created.
     */
    public ?string $created_at = null;
}
