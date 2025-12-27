<?php

declare(strict_types=1);

namespace Murmur\Entity;

use Moonspot\ValueObjects\ValueObject;

/**
 * PostAttachment entity representing media attached to a post.
 *
 * Maps to the `post_attachments` table in the database. Each post can have
 * multiple attachments (images or videos), ordered by `sort_order`. The number
 * of attachments per post is limited by the `max_attachments` admin setting.
 */
class PostAttachment extends ValueObject {

    /**
     * Primary key identifier for the attachment.
     */
    public ?int $attachment_id = null;

    /**
     * Foreign key to the post this attachment belongs to.
     */
    public int $post_id = 0;

    /**
     * Path to the stored media file (relative to uploads directory).
     */
    public string $file_path = '';

    /**
     * Type of media: 'image' or 'video'.
     */
    public string $media_type = 'image';

    /**
     * Sort order for displaying multiple attachments.
     * Lower values appear first.
     */
    public int $sort_order = 0;

    /**
     * Timestamp when the attachment was created.
     */
    public ?string $created_at = null;
}
