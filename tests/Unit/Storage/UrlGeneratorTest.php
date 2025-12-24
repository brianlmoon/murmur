<?php

declare(strict_types=1);

namespace Murmur\Tests\Unit\Storage;

use Murmur\Storage\UrlGenerator;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for UrlGenerator.
 */
class UrlGeneratorTest extends TestCase {

    public function testGenerateWithLocalAdapter(): void {
        $generator = new UrlGenerator('local', '/uploads');

        $result = $generator->generate('posts/image.jpg');

        $this->assertEquals('/uploads/posts/image.jpg', $result);
    }

    public function testGenerateWithS3Adapter(): void {
        $generator = new UrlGenerator('s3', 'https://bucket.s3.us-east-1.amazonaws.com');

        $result = $generator->generate('posts/image.jpg');

        $this->assertEquals('https://bucket.s3.us-east-1.amazonaws.com/posts/image.jpg', $result);
    }

    public function testGenerateTrimsTrailingSlashFromBaseUrl(): void {
        $generator = new UrlGenerator('local', '/uploads/');

        $result = $generator->generate('posts/image.jpg');

        $this->assertEquals('/uploads/posts/image.jpg', $result);
    }

    public function testGenerateTrimsLeadingSlashFromPath(): void {
        $generator = new UrlGenerator('local', '/uploads');

        $result = $generator->generate('/posts/image.jpg');

        $this->assertEquals('/uploads/posts/image.jpg', $result);
    }

    public function testGenerateHandlesBothSlashes(): void {
        $generator = new UrlGenerator('local', '/uploads/');

        $result = $generator->generate('/posts/image.jpg');

        $this->assertEquals('/uploads/posts/image.jpg', $result);
    }

    public function testGetAdapter(): void {
        $generator = new UrlGenerator('s3', 'https://example.com');

        $result = $generator->getAdapter();

        $this->assertEquals('s3', $result);
    }

    public function testGetBaseUrl(): void {
        $generator = new UrlGenerator('local', '/uploads');

        $result = $generator->getBaseUrl();

        $this->assertEquals('/uploads', $result);
    }

    public function testGetBaseUrlTrimsTrailingSlash(): void {
        $generator = new UrlGenerator('local', '/uploads/');

        $result = $generator->getBaseUrl();

        $this->assertEquals('/uploads', $result);
    }
}
