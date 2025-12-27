<?php

declare(strict_types=1);

namespace Murmur\Tests\Unit\Service;

use Murmur\Service\ImageService;
use Murmur\Storage\StorageInterface;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for ImageService.
 */
class ImageServiceTest extends TestCase {

    protected ImageService $image_service;

    protected StorageInterface $storage;

    protected function setUp(): void {
        $this->storage = $this->createMock(StorageInterface::class);
        $this->image_service = new ImageService($this->storage);
    }

    public function testHasUploadTrue(): void {
        $file = [
            'error' => UPLOAD_ERR_OK,
        ];

        $result = $this->image_service->hasUpload($file);

        $this->assertTrue($result);
    }

    public function testHasUploadFalseNoFile(): void {
        $file = [
            'error' => UPLOAD_ERR_NO_FILE,
        ];

        $result = $this->image_service->hasUpload($file);

        $this->assertFalse($result);
    }

    public function testHasUploadFalseNull(): void {
        $result = $this->image_service->hasUpload(null);

        $this->assertFalse($result);
    }

    public function testUploadErrorIniSize(): void {
        $file = [
            'name'     => 'test.jpg',
            'type'     => 'image/jpeg',
            'tmp_name' => '/tmp/test.jpg',
            'error'    => UPLOAD_ERR_INI_SIZE,
            'size'     => 10000000,
        ];

        $result = $this->image_service->upload($file);

        $this->assertFalse($result['success']);
        $this->assertEquals('File exceeds the server upload limit.', $result['error']);
    }

    public function testUploadErrorFormSize(): void {
        $file = [
            'name'     => 'test.jpg',
            'type'     => 'image/jpeg',
            'tmp_name' => '/tmp/test.jpg',
            'error'    => UPLOAD_ERR_FORM_SIZE,
            'size'     => 10000000,
        ];

        $result = $this->image_service->upload($file);

        $this->assertFalse($result['success']);
        $this->assertEquals('File exceeds the form upload limit.', $result['error']);
    }

    public function testUploadErrorPartial(): void {
        $file = [
            'name'     => 'test.jpg',
            'type'     => 'image/jpeg',
            'tmp_name' => '/tmp/test.jpg',
            'error'    => UPLOAD_ERR_PARTIAL,
            'size'     => 1000,
        ];

        $result = $this->image_service->upload($file);

        $this->assertFalse($result['success']);
        $this->assertEquals('File was only partially uploaded.', $result['error']);
    }

    public function testUploadFileTooLarge(): void {
        $file = [
            'name'     => 'test.jpg',
            'type'     => 'image/jpeg',
            'tmp_name' => '/tmp/test.jpg',
            'error'    => UPLOAD_ERR_OK,
            'size'     => 6 * 1024 * 1024, // 6MB
        ];

        $result = $this->image_service->upload($file);

        $this->assertFalse($result['success']);
        $this->assertEquals('File is too large. Maximum size is 5MB.', $result['error']);
    }

    public function testUploadInvalidMimeType(): void {
        $file = [
            'name'     => 'test.txt',
            'type'     => 'text/plain',
            'tmp_name' => '/tmp/test.txt',
            'error'    => UPLOAD_ERR_OK,
            'size'     => 1000,
        ];

        $result = $this->image_service->upload($file);

        $this->assertFalse($result['success']);
        $this->assertEquals('Invalid file type. Allowed: JPEG, PNG, GIF, WebP.', $result['error']);
    }

    public function testDeleteCallsStorage(): void {
        $this->storage
            ->expects($this->once())
            ->method('delete')
            ->with('posts/image.jpg')
            ->willReturn(true);

        $result = $this->image_service->delete('posts/image.jpg');

        $this->assertTrue($result);
    }

    public function testDeleteReturnsStorageResult(): void {
        $this->storage
            ->expects($this->once())
            ->method('delete')
            ->willReturn(true);

        $result = $this->image_service->delete('nonexistent/file.jpg');

        $this->assertTrue($result);
    }

    public function testGetUrl(): void {
        $this->storage
            ->expects($this->once())
            ->method('getUrl')
            ->with('posts/test.jpg')
            ->willReturn('/uploads/posts/test.jpg');

        $result = $this->image_service->getUrl('posts/test.jpg');

        $this->assertEquals('/uploads/posts/test.jpg', $result);
    }

    public function testGetUrlWithS3(): void {
        $this->storage
            ->expects($this->once())
            ->method('getUrl')
            ->with('posts/test.jpg')
            ->willReturn('https://bucket.s3.us-east-1.amazonaws.com/posts/test.jpg');

        $result = $this->image_service->getUrl('posts/test.jpg');

        $this->assertEquals('https://bucket.s3.us-east-1.amazonaws.com/posts/test.jpg', $result);
    }

    public function testUploadErrorNoFile(): void {
        $file = [
            'name'     => '',
            'type'     => '',
            'tmp_name' => '',
            'error'    => UPLOAD_ERR_NO_FILE,
            'size'     => 0,
        ];

        $result = $this->image_service->upload($file);

        $this->assertFalse($result['success']);
        $this->assertEquals('No file was uploaded.', $result['error']);
    }

    public function testUploadErrorNoTmpDir(): void {
        $file = [
            'name'     => 'test.jpg',
            'type'     => 'image/jpeg',
            'tmp_name' => '/tmp/test.jpg',
            'error'    => UPLOAD_ERR_NO_TMP_DIR,
            'size'     => 1000,
        ];

        $result = $this->image_service->upload($file);

        $this->assertFalse($result['success']);
        $this->assertEquals('Server configuration error: missing temp directory.', $result['error']);
    }

    public function testUploadErrorCantWrite(): void {
        $file = [
            'name'     => 'test.jpg',
            'type'     => 'image/jpeg',
            'tmp_name' => '/tmp/test.jpg',
            'error'    => UPLOAD_ERR_CANT_WRITE,
            'size'     => 1000,
        ];

        $result = $this->image_service->upload($file);

        $this->assertFalse($result['success']);
        $this->assertEquals('Server configuration error: cannot write to disk.', $result['error']);
    }

    public function testUploadErrorExtension(): void {
        $file = [
            'name'     => 'test.jpg',
            'type'     => 'image/jpeg',
            'tmp_name' => '/tmp/test.jpg',
            'error'    => UPLOAD_ERR_EXTENSION,
            'size'     => 1000,
        ];

        $result = $this->image_service->upload($file);

        $this->assertFalse($result['success']);
        $this->assertEquals('Upload blocked by server extension.', $result['error']);
    }

    public function testEnrichPostsWithUrls(): void {
        $attachment1 = new \stdClass();
        $attachment1->file_path = 'posts/image.jpg';

        $author = new \stdClass();
        $author->avatar_path = 'avatars/avatar.jpg';

        $post = new \stdClass();

        $posts = [
            ['post' => $post, 'author' => $author, 'attachments' => [$attachment1]],
        ];

        $this->storage
            ->expects($this->exactly(2))
            ->method('getUrl')
            ->willReturnCallback(function ($path) {
                return '/uploads/' . $path;
            });

        $result = $this->image_service->enrichPostsWithUrls($posts);

        $this->assertCount(1, $result);
        $this->assertIsArray($result[0]['image_urls']);
        $this->assertCount(1, $result[0]['image_urls']);
        $this->assertEquals('/uploads/posts/image.jpg', $result[0]['image_urls'][0]);
        $this->assertEquals('/uploads/avatars/avatar.jpg', $result[0]['avatar_url']);
    }

    public function testEnrichPostsWithUrlsNoAttachments(): void {
        $author = new \stdClass();
        $author->avatar_path = null;

        $post = new \stdClass();

        $posts = [
            ['post' => $post, 'author' => $author, 'attachments' => []],
        ];

        $result = $this->image_service->enrichPostsWithUrls($posts);

        $this->assertCount(1, $result);
        $this->assertIsArray($result[0]['image_urls']);
        $this->assertEmpty($result[0]['image_urls']);
        $this->assertNull($result[0]['avatar_url']);
    }

    public function testEnrichPostsWithUrlsMultipleAttachments(): void {
        $attachment1 = new \stdClass();
        $attachment1->file_path = 'posts/image1.jpg';

        $attachment2 = new \stdClass();
        $attachment2->file_path = 'posts/image2.jpg';

        $author = new \stdClass();
        $author->avatar_path = null;

        $post = new \stdClass();

        $posts = [
            ['post' => $post, 'author' => $author, 'attachments' => [$attachment1, $attachment2]],
        ];

        $this->storage
            ->expects($this->exactly(2))
            ->method('getUrl')
            ->willReturnCallback(function ($path) {
                return '/uploads/' . $path;
            });

        $result = $this->image_service->enrichPostsWithUrls($posts);

        $this->assertCount(1, $result);
        $this->assertCount(2, $result[0]['image_urls']);
        $this->assertEquals('/uploads/posts/image1.jpg', $result[0]['image_urls'][0]);
        $this->assertEquals('/uploads/posts/image2.jpg', $result[0]['image_urls'][1]);
        $this->assertNull($result[0]['avatar_url']);
    }

    public function testEnrichPostsWithUrlsEmptyArray(): void {
        $result = $this->image_service->enrichPostsWithUrls([]);

        $this->assertCount(0, $result);
    }
}
