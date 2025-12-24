<?php

declare(strict_types=1);

namespace Murmur\Tests\Unit\Storage;

use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemException;
use League\Flysystem\UnableToDeleteFile;
use League\Flysystem\UnableToReadFile;
use League\Flysystem\UnableToWriteFile;
use Murmur\Storage\FlysystemStorage;
use Murmur\Storage\UrlGenerator;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for FlysystemStorage.
 */
class FlysystemStorageTest extends TestCase {

    protected Filesystem $filesystem;

    protected UrlGenerator $url_generator;

    protected FlysystemStorage $storage;

    protected function setUp(): void {
        $this->filesystem = $this->createMock(Filesystem::class);
        $this->url_generator = new UrlGenerator('local', '/uploads');
        $this->storage = new FlysystemStorage($this->filesystem, $this->url_generator);
    }

    public function testWriteSuccess(): void {
        $this->filesystem
            ->expects($this->once())
            ->method('write')
            ->with('posts/image.jpg', 'content');

        $result = $this->storage->write('posts/image.jpg', 'content');

        $this->assertTrue($result);
    }

    public function testWriteFailure(): void {
        $this->filesystem
            ->expects($this->once())
            ->method('write')
            ->willThrowException(new UnableToWriteFile('Test error'));

        $result = $this->storage->write('posts/image.jpg', 'content');

        $this->assertFalse($result);
    }

    public function testWriteFromPathSuccess(): void {
        $temp_file = sys_get_temp_dir() . '/test_upload_' . uniqid() . '.txt';
        file_put_contents($temp_file, 'test content');

        $this->filesystem
            ->expects($this->once())
            ->method('write')
            ->with('posts/file.txt', 'test content');

        $result = $this->storage->writeFromPath('posts/file.txt', $temp_file);

        $this->assertTrue($result);

        unlink($temp_file);
    }

    public function testWriteFromPathFailsWhenFileNotReadable(): void {
        $result = $this->storage->writeFromPath('posts/file.txt', '/nonexistent/path.txt');

        $this->assertFalse($result);
    }

    public function testReadSuccess(): void {
        $this->filesystem
            ->expects($this->once())
            ->method('read')
            ->with('posts/image.jpg')
            ->willReturn('file content');

        $result = $this->storage->read('posts/image.jpg');

        $this->assertEquals('file content', $result);
    }

    public function testReadFailure(): void {
        $this->filesystem
            ->expects($this->once())
            ->method('read')
            ->willThrowException(new UnableToReadFile('Test error'));

        $result = $this->storage->read('posts/image.jpg');

        $this->assertNull($result);
    }

    public function testDeleteSuccess(): void {
        $this->filesystem
            ->expects($this->once())
            ->method('delete')
            ->with('posts/image.jpg');

        $result = $this->storage->delete('posts/image.jpg');

        $this->assertTrue($result);
    }

    public function testDeleteReturnsSuccessWhenFileDoesNotExist(): void {
        $this->filesystem
            ->expects($this->once())
            ->method('delete')
            ->willThrowException(new UnableToDeleteFile('Test error'));

        $result = $this->storage->delete('posts/image.jpg');

        $this->assertTrue($result);
    }

    public function testExistsReturnsTrue(): void {
        $this->filesystem
            ->expects($this->once())
            ->method('fileExists')
            ->with('posts/image.jpg')
            ->willReturn(true);

        $result = $this->storage->exists('posts/image.jpg');

        $this->assertTrue($result);
    }

    public function testExistsReturnsFalse(): void {
        $this->filesystem
            ->expects($this->once())
            ->method('fileExists')
            ->with('posts/image.jpg')
            ->willReturn(false);

        $result = $this->storage->exists('posts/image.jpg');

        $this->assertFalse($result);
    }

    public function testExistsReturnsFalseOnError(): void {
        $exception = $this->createMock(FilesystemException::class);

        $this->filesystem
            ->expects($this->once())
            ->method('fileExists')
            ->willThrowException($exception);

        $result = $this->storage->exists('posts/image.jpg');

        $this->assertFalse($result);
    }

    public function testGetUrl(): void {
        $result = $this->storage->getUrl('posts/image.jpg');

        $this->assertEquals('/uploads/posts/image.jpg', $result);
    }

    public function testGetFilesystem(): void {
        $result = $this->storage->getFilesystem();

        $this->assertSame($this->filesystem, $result);
    }
}
