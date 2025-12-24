<?php

declare(strict_types=1);

namespace Murmur\Storage;

use Aws\S3\S3Client;
use League\Flysystem\AwsS3V3\AwsS3V3Adapter;
use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;

/**
 * Factory for creating storage instances from configuration.
 *
 * Reads storage configuration and instantiates the appropriate
 * Flysystem adapter and URL generator. Supports local filesystem
 * and Amazon S3 (including S3-compatible services like MinIO,
 * DigitalOcean Spaces, Backblaze B2, and Cloudflare R2).
 *
 * Usage:
 * ```php
 * // Local storage
 * $storage = StorageFactory::create([
 *     'adapter'    => 'local',
 *     'local_path' => '/path/to/uploads',
 *     'base_url'   => '/uploads',
 * ]);
 *
 * // S3 storage
 * $storage = StorageFactory::create([
 *     'adapter'    => 's3',
 *     's3_key'     => 'AKIAIOSFODNN7EXAMPLE',
 *     's3_secret'  => 'wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY',
 *     's3_region'  => 'us-east-1',
 *     's3_bucket'  => 'my-bucket',
 *     'base_url'   => 'https://my-bucket.s3.us-east-1.amazonaws.com',
 * ]);
 * ```
 */
class StorageFactory {

    /**
     * Creates a storage instance from configuration.
     *
     * @param array{
     *     adapter?: string,
     *     local_path?: string,
     *     base_url?: string,
     *     s3_key?: string,
     *     s3_secret?: string,
     *     s3_region?: string,
     *     s3_bucket?: string,
     *     s3_endpoint?: string
     * } $config Storage configuration array.
     *
     * @return StorageInterface Configured storage instance.
     *
     * @throws \InvalidArgumentException If adapter type is unsupported or
     *                                   required configuration is missing.
     */
    public static function create(array $config): StorageInterface {
        $adapter_type = $config['adapter'] ?? 'local';
        $base_url = $config['base_url'] ?? '/uploads';

        $filesystem = match ($adapter_type) {
            'local' => self::createLocalFilesystem($config),
            's3'    => self::createS3Filesystem($config),
            default => throw new \InvalidArgumentException(
                "Unsupported storage adapter: {$adapter_type}"
            ),
        };

        $url_generator = new UrlGenerator($adapter_type, $base_url);

        return new FlysystemStorage($filesystem, $url_generator);
    }

    /**
     * Creates a local filesystem adapter.
     *
     * @param array{local_path?: string} $config Configuration array.
     *
     * @return Filesystem Configured filesystem instance.
     *
     * @throws \InvalidArgumentException If local_path is not set.
     */
    protected static function createLocalFilesystem(array $config): Filesystem {
        $path = $config['local_path'] ?? null;

        if ($path === null) {
            throw new \InvalidArgumentException(
                'Local storage requires local_path configuration'
            );
        }

        $adapter = new LocalFilesystemAdapter($path);

        return new Filesystem($adapter);
    }

    /**
     * Creates an S3 filesystem adapter.
     *
     * Supports Amazon S3 and S3-compatible services by allowing
     * a custom endpoint to be specified.
     *
     * @param array{
     *     s3_key?: string,
     *     s3_secret?: string,
     *     s3_region?: string,
     *     s3_bucket?: string,
     *     s3_endpoint?: string
     * } $config Configuration array.
     *
     * @return Filesystem Configured filesystem instance.
     *
     * @throws \InvalidArgumentException If required S3 config is missing.
     */
    protected static function createS3Filesystem(array $config): Filesystem {
        $required = ['s3_key', 's3_secret', 's3_region', 's3_bucket'];

        foreach ($required as $key) {
            if (empty($config[$key])) {
                throw new \InvalidArgumentException(
                    "S3 storage requires {$key} configuration"
                );
            }
        }

        $client_config = [
            'credentials' => [
                'key'    => $config['s3_key'],
                'secret' => $config['s3_secret'],
            ],
            'region'  => $config['s3_region'],
            'version' => 'latest',
        ];

        // Support custom endpoints (MinIO, DigitalOcean Spaces, etc.)
        if (!empty($config['s3_endpoint'])) {
            $client_config['endpoint'] = $config['s3_endpoint'];
            $client_config['use_path_style_endpoint'] = true;
        }

        $client = new S3Client($client_config);
        $adapter = new AwsS3V3Adapter($client, $config['s3_bucket']);

        return new Filesystem($adapter);
    }
}
