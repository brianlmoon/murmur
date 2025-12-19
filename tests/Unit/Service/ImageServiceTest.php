<?php

declare(strict_types=1);

namespace Murmur\Tests\Unit\Service;

use Murmur\Service\ImageService;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for ImageService.
 */
class ImageServiceTest extends TestCase {

    protected ImageService $image_service;

    protected string $test_upload_dir;

    protected function setUp(): void {
        $this->test_upload_dir = sys_get_temp_dir() . '/murmur_test_uploads';
        $this->image_service = new ImageService($this->test_upload_dir);

        // Create test directory
        if (!is_dir($this->test_upload_dir)) {
            mkdir($this->test_upload_dir, 0755, true);
        }
    }

    protected function tearDown(): void {
        // Clean up test files
        $this->recursiveDelete($this->test_upload_dir);
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
            'name' => 'test.jpg',
            'type' => 'image/jpeg',
            'tmp_name' => '/tmp/test.jpg',
            'error' => UPLOAD_ERR_INI_SIZE,
            'size' => 10000000,
        ];

        $result = $this->image_service->upload($file);

        $this->assertFalse($result['success']);
        $this->assertEquals('File exceeds the server upload limit.', $result['error']);
    }

    public function testUploadErrorFormSize(): void {
        $file = [
            'name' => 'test.jpg',
            'type' => 'image/jpeg',
            'tmp_name' => '/tmp/test.jpg',
            'error' => UPLOAD_ERR_FORM_SIZE,
            'size' => 10000000,
        ];

        $result = $this->image_service->upload($file);

        $this->assertFalse($result['success']);
        $this->assertEquals('File exceeds the form upload limit.', $result['error']);
    }

    public function testUploadErrorPartial(): void {
        $file = [
            'name' => 'test.jpg',
            'type' => 'image/jpeg',
            'tmp_name' => '/tmp/test.jpg',
            'error' => UPLOAD_ERR_PARTIAL,
            'size' => 1000,
        ];

        $result = $this->image_service->upload($file);

        $this->assertFalse($result['success']);
        $this->assertEquals('File was only partially uploaded.', $result['error']);
    }

    public function testUploadFileTooLarge(): void {
        $file = [
            'name' => 'test.jpg',
            'type' => 'image/jpeg',
            'tmp_name' => '/tmp/test.jpg',
            'error' => UPLOAD_ERR_OK,
            'size' => 6 * 1024 * 1024, // 6MB
        ];

        $result = $this->image_service->upload($file);

        $this->assertFalse($result['success']);
        $this->assertEquals('File is too large. Maximum size is 5MB.', $result['error']);
    }

    public function testUploadInvalidMimeType(): void {
        $file = [
            'name' => 'test.txt',
            'type' => 'text/plain',
            'tmp_name' => '/tmp/test.txt',
            'error' => UPLOAD_ERR_OK,
            'size' => 1000,
        ];

        $result = $this->image_service->upload($file);

        $this->assertFalse($result['success']);
        $this->assertEquals('Invalid file type. Allowed: JPEG, PNG, GIF, WebP.', $result['error']);
    }

    public function testDeleteExistingFile(): void {
        // Create a test file
        $subdir = $this->test_upload_dir . '/test';
        mkdir($subdir, 0755, true);
        $test_file = 'test/testfile.jpg';
        file_put_contents($this->test_upload_dir . '/' . $test_file, 'test content');

        $this->assertTrue(file_exists($this->test_upload_dir . '/' . $test_file));

        $result = $this->image_service->delete($test_file);

        $this->assertTrue($result);
        $this->assertFalse(file_exists($this->test_upload_dir . '/' . $test_file));
    }

    public function testDeleteNonexistentFile(): void {
        $result = $this->image_service->delete('nonexistent/file.jpg');

        $this->assertFalse($result);
    }

    public function testGetFullPath(): void {
        $result = $this->image_service->getFullPath('posts/test.jpg');

        $this->assertEquals($this->test_upload_dir . '/posts/test.jpg', $result);
    }
}
