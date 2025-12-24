<?php

declare(strict_types=1);

namespace Murmur\Storage;

/**
 * Generates public URLs for stored files.
 *
 * Supports multiple storage backends with different URL patterns.
 * The base URL is configured per-adapter to support local paths,
 * S3 bucket URLs, CDN origins, and other URL schemes.
 */
class UrlGenerator {

    /**
     * Storage adapter type (local, s3, azure, gcs).
     */
    protected string $adapter;

    /**
     * Base URL for file access.
     *
     * Examples:
     * - Local: `/uploads` or `https://example.com/uploads`
     * - S3: `https://bucket-name.s3.us-east-1.amazonaws.com`
     */
    protected string $base_url;

    /**
     * Creates a new UrlGenerator instance.
     *
     * @param string $adapter  Storage adapter type (local, s3, etc.).
     * @param string $base_url Base URL for file access.
     */
    public function __construct(string $adapter, string $base_url) {
        $this->adapter = $adapter;
        $this->base_url = rtrim($base_url, '/');
    }

    /**
     * Generates the public URL for a file path.
     *
     * @param string $path Relative storage path.
     *
     * @return string Public URL.
     */
    public function generate(string $path): string {
        return $this->base_url . '/' . ltrim($path, '/');
    }

    /**
     * Gets the configured adapter type.
     *
     * @return string Adapter type (local, s3, etc.).
     */
    public function getAdapter(): string {
        return $this->adapter;
    }

    /**
     * Gets the configured base URL.
     *
     * @return string Base URL.
     */
    public function getBaseUrl(): string {
        return $this->base_url;
    }
}
