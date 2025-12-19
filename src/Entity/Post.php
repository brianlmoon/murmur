<?php

declare(strict_types=1);

namespace Murmur\Entity;

use Moonspot\ValueObjects\ValueObject;

/**
 * Post entity representing a user's post or reply.
 *
 * Maps to the `posts` table in the database.
 */
class Post extends ValueObject {

    /**
     * Primary key identifier for the post.
     */
    public ?int $post_id = null;

    /**
     * Foreign key to the user who created this post.
     */
    public int $user_id = 0;

    /**
     * Foreign key to parent post if this is a reply.
     * Null indicates a top-level post.
     */
    public ?int $parent_id = null;

    /**
     * Foreign key to topic for categorization.
     * Null indicates no topic assigned.
     * Only applies to top-level posts, not replies.
     */
    public ?int $topic_id = null;

    /**
     * Text content of the post.
     */
    public string $body = '';

    /**
     * Path to an attached image, if any.
     */
    public ?string $image_path = null;

    /**
     * Timestamp when the post was created.
     */
    public ?string $created_at = null;

    /**
     * Timestamp when the post was last updated.
     */
    public ?string $updated_at = null;
}
