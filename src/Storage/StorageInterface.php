<?php

declare(strict_types=1);

namespace Murmur\Storage;

/**
 * Contract for file storage operations.
 *
 * Implementations handle reading, writing, and deleting files
 * from various backends (local filesystem, S3, Azure, GCS).
 */
interface StorageInterface {

    /**
     * Writes content to a file in storage.
     *
     * @param string $path    Relative path within storage.
     * @param string $content File content to write.
     *
     * @return bool True on success, false on failure.
     */
    public function write(string $path, string $content): bool;

    /**
     * Writes a file from a local filesystem path to storage.
     *
     * Used primarily for handling uploaded files where the content
     * exists in a temporary file on the local filesystem.
     *
     * @param string $path       Relative path within storage.
     * @param string $local_path Local filesystem path to read from.
     *
     * @return bool True on success, false on failure.
     */
    public function writeFromPath(string $path, string $local_path): bool;

    /**
     * Reads a file from storage.
     *
     * @param string $path Relative path within storage.
     *
     * @return string|null File content or null if not found.
     */
    public function read(string $path): ?string;

    /**
     * Deletes a file from storage.
     *
     * This method is idempotent - deleting a non-existent file
     * returns true rather than failing.
     *
     * @param string $path Relative path within storage.
     *
     * @return bool True on success or if file didn't exist.
     */
    public function delete(string $path): bool;

    /**
     * Checks if a file exists in storage.
     *
     * @param string $path Relative path within storage.
     *
     * @return bool True if file exists.
     */
    public function exists(string $path): bool;

    /**
     * Gets the public URL for a stored file.
     *
     * The URL format varies by storage backend:
     * - Local: `/uploads/path/to/file.jpg`
     * - S3: `https://bucket.s3.region.amazonaws.com/path/to/file.jpg`
     *
     * @param string $path Relative path within storage.
     *
     * @return string Public URL to access the file.
     */
    public function getUrl(string $path): string;
}
