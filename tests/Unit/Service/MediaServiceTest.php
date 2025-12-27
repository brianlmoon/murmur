<?php

declare(strict_types=1);

namespace Murmur\Tests\Unit\Service;

use Murmur\Service\MediaService;
use Murmur\Storage\StorageInterface;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for MediaService.
 */
class MediaServiceTest extends TestCase {

    protected MediaService $media_service;

    protected StorageInterface $storage;

    protected function setUp(): void {
        $this->storage = $this->createMock(StorageInterface::class);
        $this->media_service = new MediaService($this->storage);
    }

    public function testHasUploadTrue(): void {
        $file = [
            'error' => UPLOAD_ERR_OK,
        ];

        $result = $this->media_service->hasUpload($file);

        $this->assertTrue($result);
    }

    public function testHasUploadFalseNoFile(): void {
        $file = [
            'error' => UPLOAD_ERR_NO_FILE,
        ];

        $result = $this->media_service->hasUpload($file);

        $this->assertFalse($result);
    }

    public function testHasUploadFalseNull(): void {
        $result = $this->media_service->hasUpload(null);

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

        $result = $this->media_service->upload($file);

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

        $result = $this->media_service->upload($file);

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

        $result = $this->media_service->upload($file);

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

        $result = $this->media_service->upload($file);

        $this->assertFalse($result['success']);
        $this->assertEquals('Image is too large. Maximum size is 5MB.', $result['error']);
    }

    public function testUploadInvalidMimeType(): void {
        $file = [
            'name'     => 'test.txt',
            'type'     => 'text/plain',
            'tmp_name' => '/tmp/test.txt',
            'error'    => UPLOAD_ERR_OK,
            'size'     => 1000,
        ];

        $result = $this->media_service->upload($file);

        $this->assertFalse($result['success']);
        $this->assertEquals('Invalid file type. Allowed: JPEG, PNG, GIF, WebP, MP4, WebM.', $result['error']);
    }

    public function testDeleteCallsStorage(): void {
        $this->storage
            ->expects($this->once())
            ->method('delete')
            ->with('posts/image.jpg')
            ->willReturn(true);

        $result = $this->media_service->delete('posts/image.jpg');

        $this->assertTrue($result);
    }

    public function testDeleteReturnsStorageResult(): void {
        $this->storage
            ->expects($this->once())
            ->method('delete')
            ->willReturn(true);

        $result = $this->media_service->delete('nonexistent/file.jpg');

        $this->assertTrue($result);
    }

    public function testGetUrl(): void {
        $this->storage
            ->expects($this->once())
            ->method('getUrl')
            ->with('posts/test.jpg')
            ->willReturn('/uploads/posts/test.jpg');

        $result = $this->media_service->getUrl('posts/test.jpg');

        $this->assertEquals('/uploads/posts/test.jpg', $result);
    }

    public function testGetUrlWithS3(): void {
        $this->storage
            ->expects($this->once())
            ->method('getUrl')
            ->with('posts/test.jpg')
            ->willReturn('https://bucket.s3.us-east-1.amazonaws.com/posts/test.jpg');

        $result = $this->media_service->getUrl('posts/test.jpg');

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

        $result = $this->media_service->upload($file);

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

        $result = $this->media_service->upload($file);

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

        $result = $this->media_service->upload($file);

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

        $result = $this->media_service->upload($file);

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

        $result = $this->media_service->enrichPostsWithUrls($posts);

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

        $result = $this->media_service->enrichPostsWithUrls($posts);

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

        $result = $this->media_service->enrichPostsWithUrls($posts);

        $this->assertCount(1, $result);
        $this->assertCount(2, $result[0]['image_urls']);
        $this->assertEquals('/uploads/posts/image1.jpg', $result[0]['image_urls'][0]);
        $this->assertEquals('/uploads/posts/image2.jpg', $result[0]['image_urls'][1]);
        $this->assertNull($result[0]['avatar_url']);
    }

    public function testEnrichPostsWithUrlsEmptyArray(): void {
        $result = $this->media_service->enrichPostsWithUrls([]);

        $this->assertCount(0, $result);
    }

    /**
     * Tests for hasUploads() with various multi-file array structures
     */
    public function testHasUploadsTrue(): void {
        $files = [
            'name'     => ['file1.jpg', 'file2.jpg'],
            'type'     => ['image/jpeg', 'image/jpeg'],
            'tmp_name' => ['/tmp/file1.jpg', '/tmp/file2.jpg'],
            'error'    => [UPLOAD_ERR_OK, UPLOAD_ERR_OK],
            'size'     => [1000, 2000],
        ];

        $result = $this->media_service->hasUploads($files);

        $this->assertTrue($result);
    }

    public function testHasUploadsTrueOneFile(): void {
        $files = [
            'name'     => ['file1.jpg'],
            'type'     => ['image/jpeg'],
            'tmp_name' => ['/tmp/file1.jpg'],
            'error'    => [UPLOAD_ERR_OK],
            'size'     => [1000],
        ];

        $result = $this->media_service->hasUploads($files);

        $this->assertTrue($result);
    }

    public function testHasUploadsTrueMixedWithNoFile(): void {
        $files = [
            'name'     => ['file1.jpg', ''],
            'type'     => ['image/jpeg', ''],
            'tmp_name' => ['/tmp/file1.jpg', ''],
            'error'    => [UPLOAD_ERR_OK, UPLOAD_ERR_NO_FILE],
            'size'     => [1000, 0],
        ];

        $result = $this->media_service->hasUploads($files);

        $this->assertTrue($result);
    }

    public function testHasUploadsFalseAllNoFile(): void {
        $files = [
            'name'     => ['', ''],
            'type'     => ['', ''],
            'tmp_name' => ['', ''],
            'error'    => [UPLOAD_ERR_NO_FILE, UPLOAD_ERR_NO_FILE],
            'size'     => [0, 0],
        ];

        $result = $this->media_service->hasUploads($files);

        $this->assertFalse($result);
    }

    public function testHasUploadsFalseNull(): void {
        $result = $this->media_service->hasUploads(null);

        $this->assertFalse($result);
    }

    public function testHasUploadsFalseMissingErrorKey(): void {
        $files = [
            'name'     => ['file1.jpg'],
            'type'     => ['image/jpeg'],
            'tmp_name' => ['/tmp/file1.jpg'],
            'size'     => [1000],
        ];

        $result = $this->media_service->hasUploads($files);

        $this->assertFalse($result);
    }

    public function testHasUploadsFalseNonArrayError(): void {
        $files = [
            'name'     => 'file1.jpg',
            'type'     => 'image/jpeg',
            'tmp_name' => '/tmp/file1.jpg',
            'error'    => UPLOAD_ERR_OK,
            'size'     => 1000,
        ];

        $result = $this->media_service->hasUploads($files);

        $this->assertFalse($result);
    }

    /**
     * Tests for uploadMultiple() method
     */
    public function testUploadMultipleSuccess(): void {
        $files = [
            'name'     => ['file1.jpg', 'file2.png'],
            'type'     => ['image/jpeg', 'image/png'],
            'tmp_name' => [__DIR__ . '/../../fixtures/test-image.jpg', __DIR__ . '/../../fixtures/test-image.png'],
            'error'    => [UPLOAD_ERR_OK, UPLOAD_ERR_OK],
            'size'     => [1000, 2000],
        ];

        // Mock storage to return success for both uploads
        $this->storage
            ->expects($this->exactly(2))
            ->method('writeFromPath')
            ->willReturn(true);

        $result = $this->media_service->uploadMultiple($files, 'posts', 10);

        $this->assertTrue($result['success']);
        $this->assertIsArray($result['paths']);
        $this->assertCount(2, $result['paths']);
        $this->assertStringStartsWith('posts/', $result['paths'][0]['path']);
        $this->assertEquals('image', $result['paths'][0]['media_type']);
        $this->assertStringStartsWith('posts/', $result['paths'][1]['path']);
        $this->assertEquals('image', $result['paths'][1]['media_type']);
    }

    public function testUploadMultipleEmptyUploads(): void {
        $files = [
            'name'     => ['', ''],
            'type'     => ['', ''],
            'tmp_name' => ['', ''],
            'error'    => [UPLOAD_ERR_NO_FILE, UPLOAD_ERR_NO_FILE],
            'size'     => [0, 0],
        ];

        $result = $this->media_service->uploadMultiple($files, 'posts', 10);

        $this->assertTrue($result['success']);
        $this->assertIsArray($result['paths']);
        $this->assertEmpty($result['paths']);
    }

    public function testUploadMultipleExceedsMaxFiles(): void {
        $files = [
            'name'     => ['file1.jpg', 'file2.jpg', 'file3.jpg'],
            'type'     => ['image/jpeg', 'image/jpeg', 'image/jpeg'],
            'tmp_name' => ['/tmp/file1.jpg', '/tmp/file2.jpg', '/tmp/file3.jpg'],
            'error'    => [UPLOAD_ERR_OK, UPLOAD_ERR_OK, UPLOAD_ERR_OK],
            'size'     => [1000, 2000, 3000],
        ];

        $result = $this->media_service->uploadMultiple($files, 'posts', 2);

        $this->assertFalse($result['success']);
        $this->assertEquals('Too many files. Maximum allowed is 2.', $result['error']);
    }

    public function testUploadMultipleValidationFailureAllOrNothing(): void {
        $files = [
            'name'     => ['file1.jpg', 'file2.txt'],
            'type'     => ['image/jpeg', 'text/plain'],
            'tmp_name' => [__DIR__ . '/../../fixtures/test-image.jpg', '/tmp/file2.txt'],
            'error'    => [UPLOAD_ERR_OK, UPLOAD_ERR_OK],
            'size'     => [1000, 2000],
        ];

        // Storage should never be called since validation fails
        $this->storage
            ->expects($this->never())
            ->method('writeFromPath');

        $result = $this->media_service->uploadMultiple($files, 'posts', 10);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('File 2:', $result['error']);
        $this->assertStringContainsString('Invalid file type', $result['error']);
    }

    public function testUploadMultipleValidationFailureMultipleErrors(): void {
        $files = [
            'name'     => ['file1.txt', 'file2.pdf'],
            'type'     => ['text/plain', 'application/pdf'],
            'tmp_name' => ['/tmp/file1.txt', '/tmp/file2.pdf'],
            'error'    => [UPLOAD_ERR_OK, UPLOAD_ERR_OK],
            'size'     => [1000, 2000],
        ];

        $result = $this->media_service->uploadMultiple($files, 'posts', 10);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('File 1:', $result['error']);
        $this->assertStringContainsString('File 2:', $result['error']);
        $this->assertStringContainsString('Invalid file type', $result['error']);
    }

    public function testUploadMultiplePartialUploadFailureWithRollback(): void {
        $files = [
            'name'     => ['file1.jpg', 'file2.jpg'],
            'type'     => ['image/jpeg', 'image/jpeg'],
            'tmp_name' => [__DIR__ . '/../../fixtures/test-image.jpg', __DIR__ . '/../../fixtures/test-image.jpg'],
            'error'    => [UPLOAD_ERR_OK, UPLOAD_ERR_OK],
            'size'     => [1000, 2000],
        ];

        // First upload succeeds, second fails
        $uploaded_path = null;
        $this->storage
            ->expects($this->exactly(2))
            ->method('writeFromPath')
            ->willReturnCallback(function ($path) use (&$uploaded_path) {
                static $call_count = 0;
                $call_count++;
                if ($call_count === 1) {
                    $uploaded_path = $path;
                    return true;
                }
                return false;
            });

        // Should call delete once to rollback the first uploaded file
        $this->storage
            ->expects($this->once())
            ->method('delete')
            ->with($this->callback(function ($path) use (&$uploaded_path) {
                return $path === $uploaded_path;
            }))
            ->willReturn(true);

        $result = $this->media_service->uploadMultiple($files, 'posts', 10);

        $this->assertFalse($result['success']);
        $this->assertEquals('Failed to save uploaded file.', $result['error']);
    }

    public function testUploadMultipleNormalizesFilesArray(): void {
        // Test that normalizeFilesArray handles the multi-file structure correctly
        $files = [
            'name'     => ['file1.jpg'],
            'type'     => ['image/jpeg'],
            'tmp_name' => [__DIR__ . '/../../fixtures/test-image.jpg'],
            'error'    => [UPLOAD_ERR_OK],
            'size'     => [1000],
        ];

        $this->storage
            ->expects($this->once())
            ->method('writeFromPath')
            ->willReturn(true);

        $result = $this->media_service->uploadMultiple($files, 'posts', 10);

        $this->assertTrue($result['success']);
        $this->assertCount(1, $result['paths']);
    }

    public function testUploadMultipleSingleFileAlreadyNormalized(): void {
        // Test normalizeFilesArray with a single file (already normalized structure)
        $files = [
            'name'     => 'file1.jpg',
            'type'     => 'image/jpeg',
            'tmp_name' => __DIR__ . '/../../fixtures/test-image.jpg',
            'error'    => UPLOAD_ERR_OK,
            'size'     => 1000,
        ];

        $this->storage
            ->expects($this->once())
            ->method('writeFromPath')
            ->willReturn(true);

        $result = $this->media_service->uploadMultiple($files, 'posts', 10);

        $this->assertTrue($result['success']);
        $this->assertCount(1, $result['paths']);
    }

    public function testUploadMultipleFileTooLarge(): void {
        $files = [
            'name'     => ['file1.jpg'],
            'type'     => ['image/jpeg'],
            'tmp_name' => [__DIR__ . '/../../fixtures/test-image.jpg'],
            'error'    => [UPLOAD_ERR_OK],
            'size'     => [6 * 1024 * 1024], // 6MB - exceeds 5MB limit
        ];

        $result = $this->media_service->uploadMultiple($files, 'posts', 10);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Image is too large', $result['error']);
    }

    public function testUploadMultipleUploadError(): void {
        $files = [
            'name'     => ['file1.jpg'],
            'type'     => ['image/jpeg'],
            'tmp_name' => ['/tmp/file1.jpg'],
            'error'    => [UPLOAD_ERR_PARTIAL],
            'size'     => [1000],
        ];

        $result = $this->media_service->uploadMultiple($files, 'posts', 10);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('File was only partially uploaded', $result['error']);
    }

    /**
     * Tests MIME type mismatch detection.
     *
     * Verifies that a PNG file claiming to be a JPEG is rejected.
     */
    public function testUploadMimeTypeMismatch(): void {
        $file = [
            'name'     => 'fake.jpg',
            'type'     => 'image/jpeg', // Claims to be JPEG
            'tmp_name' => __DIR__ . '/../../fixtures/test-image.png', // Actually PNG
            'error'    => UPLOAD_ERR_OK,
            'size'     => 1000,
        ];

        $result = $this->media_service->upload($file);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('MIME type mismatch', $result['error']);
    }

    /**
     * Tests MIME type mismatch in the reverse direction.
     *
     * Verifies that a JPEG file claiming to be a PNG is rejected.
     */
    public function testUploadMimeTypeMismatchReverse(): void {
        $file = [
            'name'     => 'fake.png',
            'type'     => 'image/png', // Claims to be PNG
            'tmp_name' => __DIR__ . '/../../fixtures/test-image.jpg', // Actually JPEG
            'error'    => UPLOAD_ERR_OK,
            'size'     => 1000,
        ];

        $result = $this->media_service->upload($file);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('MIME type mismatch', $result['error']);
    }

    /**
     * Tests that a valid image with matching MIME type succeeds.
     *
     * Verifies the happy path still works after adding MIME validation.
     */
    public function testUploadValidImageMatchingMime(): void {
        $file = [
            'name'     => 'valid.jpg',
            'type'     => 'image/jpeg',
            'tmp_name' => __DIR__ . '/../../fixtures/test-image.jpg',
            'error'    => UPLOAD_ERR_OK,
            'size'     => 1000,
        ];

        $this->storage
            ->expects($this->once())
            ->method('writeFromPath')
            ->willReturn(true);

        $result = $this->media_service->upload($file);

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('path', $result);
        $this->assertStringStartsWith('posts/', $result['path']);
        $this->assertStringEndsWith('.jpg', $result['path']);
    }

    /**
     * Tests that a valid PNG with matching MIME type succeeds.
     */
    public function testUploadValidPngMatchingMime(): void {
        $file = [
            'name'     => 'valid.png',
            'type'     => 'image/png',
            'tmp_name' => __DIR__ . '/../../fixtures/test-image.png',
            'error'    => UPLOAD_ERR_OK,
            'size'     => 1000,
        ];

        $this->storage
            ->expects($this->once())
            ->method('writeFromPath')
            ->willReturn(true);

        $result = $this->media_service->upload($file);

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('path', $result);
        $this->assertStringEndsWith('.png', $result['path']);
    }

    /**
     * Tests uploading a valid MP4 video.
     */
    public function testUploadVideoSuccess(): void {
        $file = [
            'name'     => 'test.mp4',
            'type'     => 'video/mp4',
            'tmp_name' => __DIR__ . '/../../fixtures/test-video.mp4',
            'error'    => UPLOAD_ERR_OK,
            'size'     => 1000,
        ];

        $this->storage
            ->expects($this->once())
            ->method('writeFromPath')
            ->willReturn(true);

        $result = $this->media_service->upload($file);

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('path', $result);
        $this->assertArrayHasKey('media_type', $result);
        $this->assertEquals('video', $result['media_type']);
        $this->assertStringEndsWith('.mp4', $result['path']);
    }

    /**
     * Tests that video exceeding max size is rejected.
     */
    public function testUploadVideoTooLarge(): void {
        // Create service with 50MB limit
        $service = new MediaService($this->storage, 50 * 1024 * 1024);

        $file = [
            'name'     => 'large.mp4',
            'type'     => 'video/mp4',
            'tmp_name' => __DIR__ . '/../../fixtures/test-video.mp4',
            'error'    => UPLOAD_ERR_OK,
            'size'     => 100 * 1024 * 1024, // 100MB exceeds 50MB limit
        ];

        $result = $service->upload($file);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Video is too large', $result['error']);
        $this->assertStringContainsString('50MB', $result['error']);
    }

    /**
     * Tests getMediaType returns correct type for image MIME.
     */
    public function testGetMediaTypeImage(): void {
        $result = $this->media_service->getMediaType('image/jpeg');
        $this->assertEquals('image', $result);
    }

    /**
     * Tests getMediaType returns correct type for video MIME.
     */
    public function testGetMediaTypeVideo(): void {
        $result = $this->media_service->getMediaType('video/mp4');
        $this->assertEquals('video', $result);
    }

    /**
     * Tests getMediaType returns unknown for unsupported MIME.
     */
    public function testGetMediaTypeUnknown(): void {
        $result = $this->media_service->getMediaType('application/pdf');
        $this->assertEquals('unknown', $result);
    }

    /**
     * Tests upload returns media_type in result.
     */
    public function testUploadReturnsMediaType(): void {
        $file = [
            'name'     => 'test.jpg',
            'type'     => 'image/jpeg',
            'tmp_name' => __DIR__ . '/../../fixtures/test-image.jpg',
            'error'    => UPLOAD_ERR_OK,
            'size'     => 1000,
        ];

        $this->storage
            ->expects($this->once())
            ->method('writeFromPath')
            ->willReturn(true);

        $result = $this->media_service->upload($file);

        $this->assertTrue($result['success']);
        $this->assertEquals('image', $result['media_type']);
    }
}
