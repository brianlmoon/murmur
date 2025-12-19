<?php

declare(strict_types=1);

namespace Murmur\Tests\Unit\Twig;

use Murmur\Twig\LinkifyExtension;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for LinkifyExtension.
 */
class LinkifyExtensionTest extends TestCase {

    protected LinkifyExtension $extension;

    protected function setUp(): void {
        $this->extension = new LinkifyExtension();
    }

    /**
     * Tests that HTTP URLs are converted to links.
     */
    public function testLinkifyHttpUrl(): void {
        $text = 'Check out http://example.com for more info';
        $result = $this->extension->linkify($text);

        $this->assertStringContainsString('<a href="http://example.com"', $result);
        $this->assertStringContainsString('target="_blank"', $result);
        $this->assertStringContainsString('rel="noopener noreferrer"', $result);
        $this->assertStringContainsString('class="post-link"', $result);
    }

    /**
     * Tests that HTTPS URLs are converted to links.
     */
    public function testLinkifyHttpsUrl(): void {
        $text = 'Visit https://secure.example.com/path';
        $result = $this->extension->linkify($text);

        $this->assertStringContainsString('<a href="https://secure.example.com/path"', $result);
    }

    /**
     * Tests that FTP URLs are converted to links.
     */
    public function testLinkifyFtpUrl(): void {
        $text = 'Download from ftp://files.example.com';
        $result = $this->extension->linkify($text);

        $this->assertStringContainsString('<a href="ftp://files.example.com"', $result);
    }

    /**
     * Tests that multiple URLs in one text are all linked.
     */
    public function testLinkifyMultipleUrls(): void {
        $text = 'See http://example.com and https://another.com';
        $result = $this->extension->linkify($text);

        $this->assertStringContainsString('href="http://example.com"', $result);
        $this->assertStringContainsString('href="https://another.com"', $result);
    }

    /**
     * Tests that URLs with query strings are handled.
     */
    public function testLinkifyUrlWithQueryString(): void {
        $text = 'Search: https://example.com/search?q=test&page=1';
        $result = $this->extension->linkify($text);

        $this->assertStringContainsString('href="https://example.com/search?q=test&amp;page=1"', $result);
    }

    /**
     * Tests that URLs with anchors are handled.
     */
    public function testLinkifyUrlWithAnchor(): void {
        $text = 'Jump to https://example.com/page#section';
        $result = $this->extension->linkify($text);

        $this->assertStringContainsString('href="https://example.com/page#section"', $result);
    }

    /**
     * Tests that URLs with ports are handled.
     */
    public function testLinkifyUrlWithPort(): void {
        $text = 'Local server at http://localhost:8080/api';
        $result = $this->extension->linkify($text);

        $this->assertStringContainsString('href="http://localhost:8080/api"', $result);
    }

    /**
     * Tests that line breaks are preserved.
     */
    public function testLinkifyPreservesLineBreaks(): void {
        $text = "Line 1\nLine 2";
        $result = $this->extension->linkify($text);

        $this->assertStringContainsString('<br />', $result);
    }

    /**
     * Tests that HTML entities are escaped (XSS protection).
     */
    public function testLinkifyEscapesHtml(): void {
        $text = 'Test <script>alert("xss")</script> attack';
        $result = $this->extension->linkify($text);

        $this->assertStringContainsString('&lt;script&gt;', $result);
        $this->assertStringContainsString('&lt;/script&gt;', $result);
        $this->assertStringNotContainsString('<script>', $result);
    }

    /**
     * Tests that null input returns empty string.
     */
    public function testLinkifyWithNullInput(): void {
        $result = $this->extension->linkify(null);

        $this->assertSame('', $result);
    }

    /**
     * Tests that text without URLs is unchanged (except HTML escaping).
     */
    public function testLinkifyWithoutUrls(): void {
        $text = 'Just plain text here';
        $result = $this->extension->linkify($text);

        $this->assertStringContainsString('Just plain text here', $result);
        $this->assertStringNotContainsString('<a', $result);
    }

    /**
     * Tests that www URLs without protocol are NOT converted.
     */
    public function testLinkifyDoesNotLinkWwwWithoutProtocol(): void {
        $text = 'Visit www.example.com for info';
        $result = $this->extension->linkify($text);

        $this->assertStringNotContainsString('<a', $result);
        $this->assertStringContainsString('www.example.com', $result);
    }

    /**
     * Tests that very long URLs are truncated in display text.
     */
    public function testLinkifyTruncatesLongUrls(): void {
        $long_url = 'https://example.com/' . str_repeat('a', 100);
        $result = $this->extension->linkify($long_url);

        // Full URL in href
        $this->assertStringContainsString('href="' . $long_url . '"', $result);

        // Truncated in display text (50 chars - 3 for ellipsis = 47 visible chars)
        $this->assertStringContainsString('...', $result);
        $this->assertStringContainsString('>https://example.com/aaaaaaaaaaaaaaaaaaaaaaaaaaa...</a>', $result);
    }

    /**
     * Tests that URLs with parentheses are handled correctly.
     */
    public function testLinkifyUrlWithParentheses(): void {
        $text = 'Wikipedia link: https://en.wikipedia.org/wiki/PHP_(programming_language)';
        $result = $this->extension->linkify($text);

        $this->assertStringContainsString('href="https://en.wikipedia.org/wiki/PHP_(programming_language)"', $result);
    }

    /**
     * Tests that getFilters returns the linkify filter.
     */
    public function testGetFiltersReturnsLinkifyFilter(): void {
        $filters = $this->extension->getFilters();

        $this->assertCount(1, $filters);
        $this->assertEquals('linkify', $filters[0]->getName());
    }

    /**
     * Tests URL at the beginning of text.
     */
    public function testLinkifyUrlAtBeginning(): void {
        $text = 'https://example.com is a great site';
        $result = $this->extension->linkify($text);

        $this->assertStringStartsWith('<a href="https://example.com"', $result);
    }

    /**
     * Tests URL at the end of text.
     */
    public function testLinkifyUrlAtEnd(): void {
        $text = 'Check this out: https://example.com';
        $result = $this->extension->linkify($text);

        $this->assertStringContainsString('href="https://example.com"', $result);
    }

    /**
     * Tests combining URL and line breaks.
     */
    public function testLinkifyUrlWithLineBreaks(): void {
        $text = "Line 1\nhttps://example.com\nLine 3";
        $result = $this->extension->linkify($text);

        $this->assertStringContainsString('<br />', $result);
        $this->assertStringContainsString('href="https://example.com"', $result);
    }
}
