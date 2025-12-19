<?php

declare(strict_types=1);

namespace Murmur\Tests\Unit\Service;

use Murmur\Entity\LinkPreview;
use Murmur\Repository\LinkPreviewMapper;
use Murmur\Service\LinkPreviewService;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for LinkPreviewService.
 *
 * Tests URL extraction, hashing, OpenGraph parsing, and security features.
 */
class LinkPreviewServiceTest extends TestCase {

    /**
     * Creates a service with a mocked mapper.
     *
     * @return LinkPreviewService The service instance.
     */
    protected function createService(): LinkPreviewService {
        $mapper = $this->createMock(LinkPreviewMapper::class);

        return new LinkPreviewService($mapper);
    }

    /**
     * Tests that extractFirstUrl finds HTTPS URLs.
     */
    public function testExtractFirstUrlFindsHttps(): void {
        $service = $this->createService();
        $text = 'Check out https://example.com for more info';

        $result = $service->extractFirstUrl($text);

        $this->assertSame('https://example.com', $result);
    }

    /**
     * Tests that extractFirstUrl finds HTTP URLs.
     */
    public function testExtractFirstUrlFindsHttp(): void {
        $service = $this->createService();
        $text = 'Visit http://example.com today';

        $result = $service->extractFirstUrl($text);

        $this->assertSame('http://example.com', $result);
    }

    /**
     * Tests that extractFirstUrl finds FTP URLs.
     */
    public function testExtractFirstUrlFindsFtp(): void {
        $service = $this->createService();
        $text = 'Download from ftp://files.example.com';

        $result = $service->extractFirstUrl($text);

        $this->assertSame('ftp://files.example.com', $result);
    }

    /**
     * Tests that extractFirstUrl returns null when no URL is found.
     */
    public function testExtractFirstUrlReturnsNullWhenNoUrl(): void {
        $service = $this->createService();
        $text = 'Just plain text without any links';

        $result = $service->extractFirstUrl($text);

        $this->assertNull($result);
    }

    /**
     * Tests that extractFirstUrl returns only the first URL.
     */
    public function testExtractFirstUrlReturnsOnlyFirst(): void {
        $service = $this->createService();
        $text = 'First: https://first.com and second: https://second.com';

        $result = $service->extractFirstUrl($text);

        $this->assertSame('https://first.com', $result);
    }

    /**
     * Tests that extractFirstUrl does not match www without protocol.
     */
    public function testExtractFirstUrlIgnoresWwwWithoutProtocol(): void {
        $service = $this->createService();
        $text = 'Visit www.example.com for more';

        $result = $service->extractFirstUrl($text);

        $this->assertNull($result);
    }

    /**
     * Tests that extractFirstUrl handles URLs with paths.
     */
    public function testExtractFirstUrlWithPath(): void {
        $service = $this->createService();
        $text = 'Read https://example.com/blog/post-title';

        $result = $service->extractFirstUrl($text);

        $this->assertSame('https://example.com/blog/post-title', $result);
    }

    /**
     * Tests that extractFirstUrl handles URLs with query strings.
     */
    public function testExtractFirstUrlWithQueryString(): void {
        $service = $this->createService();
        $text = 'Search https://example.com/search?q=test&page=1';

        $result = $service->extractFirstUrl($text);

        $this->assertSame('https://example.com/search?q=test&page=1', $result);
    }

    /**
     * Tests that extractFirstUrl handles URLs with anchors.
     */
    public function testExtractFirstUrlWithAnchor(): void {
        $service = $this->createService();
        $text = 'Jump to https://example.com/page#section';

        $result = $service->extractFirstUrl($text);

        $this->assertSame('https://example.com/page#section', $result);
    }

    /**
     * Tests that hashUrl normalizes case.
     */
    public function testHashUrlNormalizesCase(): void {
        $service = $this->createService();

        $hash1 = $service->hashUrl('https://EXAMPLE.COM/Path');
        $hash2 = $service->hashUrl('https://example.com/Path');

        $this->assertSame($hash1, $hash2);
    }

    /**
     * Tests that hashUrl treats different paths as different.
     */
    public function testHashUrlDifferentPaths(): void {
        $service = $this->createService();

        $hash1 = $service->hashUrl('https://example.com/page1');
        $hash2 = $service->hashUrl('https://example.com/page2');

        $this->assertNotSame($hash1, $hash2);
    }

    /**
     * Tests that hashUrl returns a 64-character SHA-256 hash.
     */
    public function testHashUrlReturnsSha256(): void {
        $service = $this->createService();

        $hash = $service->hashUrl('https://example.com');

        $this->assertSame(64, strlen($hash));
        $this->assertMatchesRegularExpression('/^[a-f0-9]+$/', $hash);
    }
}
