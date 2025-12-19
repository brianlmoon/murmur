<?php

declare(strict_types=1);

namespace Murmur\Entity;

use Moonspot\ValueObjects\ValueObject;

/**
 * Link preview entity for cached URL metadata.
 *
 * Stores OpenGraph and meta tag data extracted from URLs
 * to display rich preview cards in posts.
 */
class LinkPreview extends ValueObject {

    /**
     * Primary key identifier.
     */
    public ?int $preview_id = null;

    /**
     * SHA-256 hash of the URL for efficient lookups.
     */
    public string $url_hash = '';

    /**
     * The original URL.
     */
    public string $url = '';

    /**
     * Page title from og:title or <title> tag.
     */
    public ?string $title = null;

    /**
     * Description from og:description or meta description.
     */
    public ?string $description = null;

    /**
     * Image URL from og:image.
     */
    public ?string $image_url = null;

    /**
     * Site name from og:site_name.
     */
    public ?string $site_name = null;

    /**
     * When the URL was fetched.
     */
    public ?string $fetched_at = null;

    /**
     * Fetch status: pending, success, failed.
     */
    public string $fetch_status = 'pending';

    /**
     * When this record was created.
     */
    public ?string $created_at = null;
}
