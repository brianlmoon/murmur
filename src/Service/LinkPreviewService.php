<?php

declare(strict_types=1);

namespace Murmur\Service;

use Murmur\Entity\LinkPreview;
use Murmur\Repository\LinkPreviewMapper;

/**
 * Service for fetching and managing URL link previews.
 *
 * Extracts OpenGraph metadata from URLs and caches results
 * for display in post preview cards. Only the first URL in a post
 * is previewed to keep the UI clean and predictable.
 */
class LinkPreviewService {

    /**
     * HTTP request timeout in seconds.
     */
    protected const FETCH_TIMEOUT = 3;

    /**
     * Maximum content length to download (1 MB).
     */
    protected const MAX_CONTENT_LENGTH = 1048576;

    /**
     * User agent for HTTP requests.
     */
    protected const USER_AGENT = 'Murmur/1.0 (Link Preview Bot)';

    /**
     * URL pattern for extraction (matches LinkifyExtension).
     */
    protected const URL_PATTERN = '#\b((?:https?|ftp)://[^\s<]+(?:\([^\s<]*\)|[^\s<]))#i';

    /**
     * Maximum length for sanitized strings.
     */
    protected const MAX_STRING_LENGTH = 500;

    /**
     * The link preview mapper for database operations.
     */
    protected LinkPreviewMapper $mapper;

    /**
     * Creates a new LinkPreviewService instance.
     *
     * @param LinkPreviewMapper $mapper The link preview mapper.
     */
    public function __construct(LinkPreviewMapper $mapper) {
        $this->mapper = $mapper;
    }

    /**
     * Extracts the first URL from text.
     *
     * Only the first URL is extracted because we display a single preview
     * card per post. This keeps the UI clean and matches behavior of
     * platforms like Twitter, Slack, and Discord.
     *
     * @param string $text The text to search.
     *
     * @return string|null The first URL or null if none found.
     */
    public function extractFirstUrl(string $text): ?string {
        $result = null;

        if (preg_match(self::URL_PATTERN, $text, $matches)) {
            $result = $matches[1];
        }

        return $result;
    }

    /**
     * Generates a hash for a URL for cache lookup.
     *
     * Normalizes the URL before hashing to ensure consistent cache hits
     * regardless of case differences in scheme or host.
     *
     * @param string $url The URL to hash.
     *
     * @return string SHA-256 hash of the normalized URL.
     */
    public function hashUrl(string $url): string {
        $parsed = parse_url($url);
        $normalized = strtolower($parsed['scheme'] ?? 'http') . '://';
        $normalized .= strtolower($parsed['host'] ?? '');
        $normalized .= $parsed['path'] ?? '/';

        if (isset($parsed['query'])) {
            $normalized .= '?' . $parsed['query'];
        }

        if (isset($parsed['fragment'])) {
            $normalized .= '#' . $parsed['fragment'];
        }

        return hash('sha256', $normalized);
    }

    /**
     * Gets or creates a preview for a URL.
     *
     * If the preview exists and is fresh, returns it immediately.
     * If not cached, creates a pending record and fetches metadata.
     * Failed fetches are cached to avoid repeated attempts.
     *
     * @param string $url The URL to preview.
     *
     * @return LinkPreview|null The preview or null if unavailable.
     */
    public function getPreview(string $url): ?LinkPreview {
        $result = null;

        if ($this->isAllowedUrl($url)) {
            $hash = $this->hashUrl($url);
            $preview = $this->mapper->findByUrlHash($hash);

            if ($preview !== null && $preview->fetch_status === 'success') {
                $result = $preview;
            } elseif ($preview === null) {
                $preview = $this->createPendingPreview($url, $hash);
                $result = $this->fetchAndUpdate($preview);
            }
        }

        return $result;
    }

    /**
     * Gets a preview for a post's first URL.
     *
     * Convenience method that extracts the first URL from post body
     * and returns its preview if available.
     *
     * @param string $body The post body text.
     *
     * @return LinkPreview|null The preview or null if no URL or unavailable.
     */
    public function getPreviewForPost(string $body): ?LinkPreview {
        $result = null;
        $url = $this->extractFirstUrl($body);

        if ($url !== null) {
            $result = $this->getPreview($url);
        }

        return $result;
    }

    /**
     * Gets previews for multiple posts efficiently.
     *
     * Batch-fetches cached previews for multiple post bodies.
     * By default, only returns successfully cached previews.
     * Set $fetch_missing to true to fetch previews for URLs not yet cached.
     *
     * @param array<int, string> $post_bodies   Map of post_id => body text.
     * @param bool               $fetch_missing Whether to fetch previews for uncached URLs.
     *
     * @return array<int, LinkPreview> Map of post_id => preview.
     */
    public function getPreviewsForPosts(array $post_bodies, bool $fetch_missing = false): array {
        $result = [];
        $url_to_post_ids = [];
        $hashes = [];

        foreach ($post_bodies as $post_id => $body) {
            $url = $this->extractFirstUrl($body);

            if ($url !== null && $this->isAllowedUrl($url)) {
                $hash = $this->hashUrl($url);
                $hashes[$hash] = $url;

                if (!isset($url_to_post_ids[$url])) {
                    $url_to_post_ids[$url] = [];
                }

                $url_to_post_ids[$url][] = $post_id;
            }
        }

        if (!empty($hashes)) {
            $cached = $this->mapper->findByUrlHashes(array_keys($hashes));

            foreach ($cached as $hash => $preview) {
                if ($preview->fetch_status === 'success') {
                    $url = $preview->url;

                    if (isset($url_to_post_ids[$url])) {
                        foreach ($url_to_post_ids[$url] as $post_id) {
                            $result[$post_id] = $preview;
                        }

                        unset($url_to_post_ids[$url]);
                    }
                }
            }

            // Fetch missing previews if requested
            if ($fetch_missing && !empty($url_to_post_ids)) {
                foreach ($url_to_post_ids as $url => $post_ids) {
                    $preview = $this->getPreview($url);

                    if ($preview !== null) {
                        foreach ($post_ids as $post_id) {
                            $result[$post_id] = $preview;
                        }
                    }
                }
            }
        }

        return $result;
    }

    /**
     * Creates a pending preview record.
     *
     * @param string $url  The URL.
     * @param string $hash The URL hash.
     *
     * @return LinkPreview The created preview.
     */
    protected function createPendingPreview(string $url, string $hash): LinkPreview {
        $preview = new LinkPreview();
        $preview->url = $url;
        $preview->url_hash = $hash;
        $preview->fetch_status = 'pending';

        $this->mapper->save($preview);

        return $preview;
    }

    /**
     * Fetches URL metadata and updates the preview.
     *
     * @param LinkPreview $preview The preview to update.
     *
     * @return LinkPreview|null The updated preview or null on failure.
     */
    public function fetchAndUpdate(LinkPreview $preview): ?LinkPreview {
        $result = null;
        $metadata = $this->fetchMetadata($preview->url);

        if ($metadata !== null && ($metadata['title'] !== null || $metadata['description'] !== null)) {
            $preview->title = $metadata['title'];
            $preview->description = $metadata['description'];
            $preview->image_url = $metadata['image'];
            $preview->site_name = $metadata['site_name'];
            $preview->fetch_status = 'success';
            $preview->fetched_at = date('Y-m-d H:i:s');

            $this->mapper->save($preview);
            $result = $preview;
        } else {
            $preview->fetch_status = 'failed';
            $preview->fetched_at = date('Y-m-d H:i:s');
            $this->mapper->save($preview);
        }

        return $result;
    }

    /**
     * Checks if a URL is allowed to be fetched.
     *
     * Blocks private IP addresses and localhost to prevent SSRF attacks.
     *
     * @param string $url The URL to check.
     *
     * @return bool True if the URL is allowed, false otherwise.
     */
    protected function isAllowedUrl(string $url): bool {
        $result = false;
        $parsed = parse_url($url);

        if ($parsed !== false && isset($parsed['host']) && isset($parsed['scheme'])) {
            $scheme = strtolower($parsed['scheme']);

            if ($scheme === 'http' || $scheme === 'https') {
                $host = $parsed['host'];
                $ip = gethostbyname($host);

                // If gethostbyname fails, it returns the hostname
                if ($ip !== $host) {
                    $result = !$this->isPrivateIp($ip);
                }
            }
        }

        return $result;
    }

    /**
     * Checks if an IP address is in a private range.
     *
     * @param string $ip The IP address to check.
     *
     * @return bool True if the IP is private, false otherwise.
     */
    protected function isPrivateIp(string $ip): bool {
        $is_private = false;

        $blocked_patterns = [
            '/^127\./',                        // Loopback
            '/^10\./',                         // Private Class A
            '/^172\.(1[6-9]|2[0-9]|3[01])\./', // Private Class B
            '/^192\.168\./',                   // Private Class C
            '/^169\.254\./',                   // Link-local
            '/^0\./',                          // Invalid
        ];

        foreach ($blocked_patterns as $pattern) {
            if (preg_match($pattern, $ip)) {
                $is_private = true;
                break;
            }
        }

        return $is_private;
    }

    /**
     * Fetches metadata from a URL.
     *
     * @param string $url The URL to fetch.
     *
     * @return array{title: ?string, description: ?string, image: ?string, site_name: ?string}|null
     */
    protected function fetchMetadata(string $url): ?array {
        $result = null;
        $html = $this->fetchHtml($url);

        if ($html !== null) {
            $result = $this->parseOpenGraph($html, $url);
        }

        return $result;
    }

    /**
     * Fetches HTML content from a URL.
     *
     * Uses a 3-second timeout to prevent hanging on slow sites.
     * Limits download size to 1 MB for safety.
     *
     * @param string $url The URL to fetch.
     *
     * @return string|null The HTML content or null on failure.
     */
    protected function fetchHtml(string $url): ?string {
        $result = null;

        $context = stream_context_create([
            'http' => [
                'timeout'         => self::FETCH_TIMEOUT,
                'user_agent'      => self::USER_AGENT,
                'follow_location' => true,
                'max_redirects'   => 3,
                'ignore_errors'   => true,
            ],
            'ssl' => [
                'verify_peer'      => true,
                'verify_peer_name' => true,
            ],
        ]);

        $content = @file_get_contents($url, false, $context, 0, self::MAX_CONTENT_LENGTH);

        if ($content !== false) {
            $result = $content;
        }

        return $result;
    }

    /**
     * Parses OpenGraph and meta tags from HTML.
     *
     * Extracts og:title, og:description, og:image, and og:site_name.
     * Falls back to standard title and meta description tags when
     * OpenGraph tags are not available.
     *
     * @param string $html     The HTML content.
     * @param string $base_url The base URL for resolving relative image paths.
     *
     * @return array{title: ?string, description: ?string, image: ?string, site_name: ?string}
     */
    protected function parseOpenGraph(string $html, string $base_url): array {
        $result = [
            'title'       => null,
            'description' => null,
            'image'       => null,
            'site_name'   => null,
        ];

        // Extract og:title or fall back to <title>
        $result['title'] = $this->extractMetaProperty($html, 'og:title');

        if ($result['title'] === null) {
            if (preg_match('/<title[^>]*>([^<]+)<\/title>/i', $html, $matches)) {
                $result['title'] = $this->sanitizeString($matches[1]);
            }
        }

        // Extract og:description or fall back to meta description
        $result['description'] = $this->extractMetaProperty($html, 'og:description');

        if ($result['description'] === null) {
            $result['description'] = $this->extractMetaName($html, 'description');
        }

        // Extract og:image
        $image_url = $this->extractMetaProperty($html, 'og:image');

        if ($image_url !== null) {
            $result['image'] = $this->resolveImageUrl($image_url, $base_url);
        }

        // Extract og:site_name
        $result['site_name'] = $this->extractMetaProperty($html, 'og:site_name');

        return $result;
    }

    /**
     * Extracts a meta property value from HTML.
     *
     * @param string $html     The HTML content.
     * @param string $property The property name (e.g., 'og:title').
     *
     * @return string|null The property value or null if not found.
     */
    protected function extractMetaProperty(string $html, string $property): ?string {
        $result = null;
        $property_escaped = preg_quote($property, '/');

        // Try property="..." content="..."
        $pattern1 = '/<meta[^>]+property=["\']' . $property_escaped . '["\'][^>]+content=["\']([^"\']+)["\'][^>]*>/i';

        // Try content="..." property="..."
        $pattern2 = '/<meta[^>]+content=["\']([^"\']+)["\'][^>]+property=["\']' . $property_escaped . '["\'][^>]*>/i';

        if (preg_match($pattern1, $html, $matches) || preg_match($pattern2, $html, $matches)) {
            $result = $this->sanitizeString($matches[1]);
        }

        return $result;
    }

    /**
     * Extracts a meta name value from HTML.
     *
     * @param string $html The HTML content.
     * @param string $name The name attribute (e.g., 'description').
     *
     * @return string|null The value or null if not found.
     */
    protected function extractMetaName(string $html, string $name): ?string {
        $result = null;
        $name_escaped = preg_quote($name, '/');

        // Try name="..." content="..."
        $pattern1 = '/<meta[^>]+name=["\']' . $name_escaped . '["\'][^>]+content=["\']([^"\']+)["\'][^>]*>/i';

        // Try content="..." name="..."
        $pattern2 = '/<meta[^>]+content=["\']([^"\']+)["\'][^>]+name=["\']' . $name_escaped . '["\'][^>]*>/i';

        if (preg_match($pattern1, $html, $matches) || preg_match($pattern2, $html, $matches)) {
            $result = $this->sanitizeString($matches[1]);
        }

        return $result;
    }

    /**
     * Resolves an image URL, handling relative paths.
     *
     * @param string $image_url The image URL (may be relative).
     * @param string $base_url  The base URL for resolving relative paths.
     *
     * @return string The resolved absolute URL.
     */
    protected function resolveImageUrl(string $image_url, string $base_url): string {
        $result = $image_url;

        if (!preg_match('#^https?://#i', $image_url)) {
            $parsed = parse_url($base_url);
            $base = ($parsed['scheme'] ?? 'https') . '://' . ($parsed['host'] ?? '');
            $result = $base . '/' . ltrim($image_url, '/');
        }

        return $result;
    }

    /**
     * Sanitizes a string extracted from HTML.
     *
     * Decodes HTML entities, strips tags, and trims whitespace.
     * Truncates to MAX_STRING_LENGTH characters.
     *
     * @param string $value The string to sanitize.
     *
     * @return string The sanitized string.
     */
    protected function sanitizeString(string $value): string {
        $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = strip_tags($value);
        $value = trim($value);

        if (mb_strlen($value) > self::MAX_STRING_LENGTH) {
            $value = mb_substr($value, 0, self::MAX_STRING_LENGTH - 3) . '...';
        }

        return $value;
    }
}
