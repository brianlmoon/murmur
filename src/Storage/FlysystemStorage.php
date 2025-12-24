<?php

declare(strict_types=1);

namespace Murmur\Storage;

use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemException;

/**
 * Flysystem-based storage implementation.
 *
 * Delegates storage operations to a Flysystem Filesystem instance,
 * enabling support for multiple storage backends including local
 * filesystem, Amazon S3, and other cloud providers.
 *
 * Usage:
 * ```php
 * $adapter = new LocalFilesystemAdapter('/path/to/uploads');
 * $filesystem = new Filesystem($adapter);
 * $url_generator = new UrlGenerator('local', '/uploads');
 * $storage = new FlysystemStorage($filesystem, $url_generator);
 *
 * $storage->write('images/photo.jpg', $content);
 * $url = $storage->getUrl('images/photo.jpg'); // "/uploads/images/photo.jpg"
 * ```
 */
class FlysystemStorage implements StorageInterface {

    /**
     * Flysystem filesystem instance.
     */
    protected Filesystem $filesystem;

    /**
     * URL generator for stored files.
     */
    protected UrlGenerator $url_generator;

    /**
     * Creates a new FlysystemStorage instance.
     *
     * @param Filesystem   $filesystem    Configured Flysystem instance.
     * @param UrlGenerator $url_generator URL generator for public URLs.
     */
    public function __construct(
        Filesystem $filesystem,
        UrlGenerator $url_generator
    ) {
        $this->filesystem = $filesystem;
        $this->url_generator = $url_generator;
    }

    /**
     * {@inheritdoc}
     */
    public function write(string $path, string $content): bool {
        $result = false;

        try {
            $this->filesystem->write($path, $content);
            $result = true;
        } catch (FilesystemException $e) {
            // Write failed - could be permissions, disk full, etc.
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function writeFromPath(string $path, string $local_path): bool {
        $result = false;

        $content = @file_get_contents($local_path);

        if ($content !== false) {
            $result = $this->write($path, $content);
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function read(string $path): ?string {
        $result = null;

        try {
            $result = $this->filesystem->read($path);
        } catch (FilesystemException $e) {
            // File doesn't exist or read error
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function delete(string $path): bool {
        $result = true;

        try {
            $this->filesystem->delete($path);
        } catch (FilesystemException $e) {
            // File didn't exist, which is acceptable for delete operations
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function exists(string $path): bool {
        $result = false;

        try {
            $result = $this->filesystem->fileExists($path);
        } catch (FilesystemException $e) {
            // Assume doesn't exist on error
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function getUrl(string $path): string {
        return $this->url_generator->generate($path);
    }

    /**
     * Gets the underlying Flysystem filesystem instance.
     *
     * Useful for advanced operations not covered by StorageInterface.
     *
     * @return Filesystem The Flysystem filesystem instance.
     */
    public function getFilesystem(): Filesystem {
        return $this->filesystem;
    }
}
