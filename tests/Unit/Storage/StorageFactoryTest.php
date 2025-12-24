<?php

declare(strict_types=1);

namespace Murmur\Tests\Unit\Storage;

use Murmur\Storage\FlysystemStorage;
use Murmur\Storage\StorageFactory;
use Murmur\Storage\StorageInterface;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for StorageFactory.
 */
class StorageFactoryTest extends TestCase {

    protected string $test_dir;

    protected function setUp(): void {
        $this->test_dir = sys_get_temp_dir() . '/murmur_storage_test_' . uniqid();
        mkdir($this->test_dir, 0755, true);
    }

    protected function tearDown(): void {
        $this->recursiveDelete($this->test_dir);
    }

    protected function recursiveDelete(string $dir): void {
        if (is_dir($dir)) {
            $files = scandir($dir);
            foreach ($files as $file) {
                if ($file !== '.' && $file !== '..') {
                    $path = $dir . '/' . $file;
                    if (is_dir($path)) {
                        $this->recursiveDelete($path);
                    } else {
                        unlink($path);
                    }
                }
            }
            rmdir($dir);
        }
    }

    public function testCreateLocalStorage(): void {
        $config = [
            'adapter'    => 'local',
            'local_path' => $this->test_dir,
            'base_url'   => '/uploads',
        ];

        $storage = StorageFactory::create($config);

        $this->assertInstanceOf(StorageInterface::class, $storage);
        $this->assertInstanceOf(FlysystemStorage::class, $storage);
    }

    public function testCreateLocalStorageDefaultsToLocal(): void {
        $config = [
            'local_path' => $this->test_dir,
            'base_url'   => '/uploads',
        ];

        $storage = StorageFactory::create($config);

        $this->assertInstanceOf(StorageInterface::class, $storage);
    }

    public function testCreateLocalStorageThrowsWithoutPath(): void {
        $config = [
            'adapter'  => 'local',
            'base_url' => '/uploads',
        ];

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Local storage requires local_path configuration');

        StorageFactory::create($config);
    }

    public function testCreateThrowsForUnsupportedAdapter(): void {
        $config = [
            'adapter' => 'unsupported',
        ];

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported storage adapter: unsupported');

        StorageFactory::create($config);
    }

    public function testCreateS3ThrowsWithoutKey(): void {
        $config = [
            'adapter'   => 's3',
            's3_secret' => 'secret',
            's3_region' => 'us-east-1',
            's3_bucket' => 'bucket',
        ];

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('S3 storage requires s3_key configuration');

        StorageFactory::create($config);
    }

    public function testCreateS3ThrowsWithoutSecret(): void {
        $config = [
            'adapter'   => 's3',
            's3_key'    => 'key',
            's3_region' => 'us-east-1',
            's3_bucket' => 'bucket',
        ];

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('S3 storage requires s3_secret configuration');

        StorageFactory::create($config);
    }

    public function testCreateS3ThrowsWithoutRegion(): void {
        $config = [
            'adapter'   => 's3',
            's3_key'    => 'key',
            's3_secret' => 'secret',
            's3_bucket' => 'bucket',
        ];

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('S3 storage requires s3_region configuration');

        StorageFactory::create($config);
    }

    public function testCreateS3ThrowsWithoutBucket(): void {
        $config = [
            'adapter'   => 's3',
            's3_key'    => 'key',
            's3_secret' => 'secret',
            's3_region' => 'us-east-1',
        ];

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('S3 storage requires s3_bucket configuration');

        StorageFactory::create($config);
    }

    public function testLocalStorageCanWriteAndRead(): void {
        $config = [
            'adapter'    => 'local',
            'local_path' => $this->test_dir,
            'base_url'   => '/uploads',
        ];

        $storage = StorageFactory::create($config);

        $write_result = $storage->write('test/file.txt', 'hello world');
        $this->assertTrue($write_result);

        $read_result = $storage->read('test/file.txt');
        $this->assertEquals('hello world', $read_result);
    }

    public function testLocalStorageUrlGeneration(): void {
        $config = [
            'adapter'    => 'local',
            'local_path' => $this->test_dir,
            'base_url'   => '/uploads',
        ];

        $storage = StorageFactory::create($config);

        $url = $storage->getUrl('posts/image.jpg');

        $this->assertEquals('/uploads/posts/image.jpg', $url);
    }

    public function testLocalStorageExists(): void {
        $config = [
            'adapter'    => 'local',
            'local_path' => $this->test_dir,
            'base_url'   => '/uploads',
        ];

        $storage = StorageFactory::create($config);

        $this->assertFalse($storage->exists('test/file.txt'));

        $storage->write('test/file.txt', 'content');

        $this->assertTrue($storage->exists('test/file.txt'));
    }

    public function testLocalStorageDelete(): void {
        $config = [
            'adapter'    => 'local',
            'local_path' => $this->test_dir,
            'base_url'   => '/uploads',
        ];

        $storage = StorageFactory::create($config);
        $storage->write('test/file.txt', 'content');

        $this->assertTrue($storage->exists('test/file.txt'));

        $delete_result = $storage->delete('test/file.txt');

        $this->assertTrue($delete_result);
        $this->assertFalse($storage->exists('test/file.txt'));
    }

    public function testDefaultBaseUrl(): void {
        $config = [
            'adapter'    => 'local',
            'local_path' => $this->test_dir,
        ];

        $storage = StorageFactory::create($config);

        $url = $storage->getUrl('posts/image.jpg');

        $this->assertEquals('/uploads/posts/image.jpg', $url);
    }
}
