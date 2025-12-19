<?php

declare(strict_types=1);

namespace Murmur\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

/**
 * Twig extension for automatically linking URLs in text.
 *
 * Provides a `linkify` filter that converts plain-text URLs into clickable
 * HTML links that open in a new tab with appropriate security attributes.
 */
class LinkifyExtension extends AbstractExtension {

    /**
     * URL detection regex pattern.
     *
     * Matches http://, https://, and ftp:// URLs.
     */
    protected const URL_PATTERN = '#\b((?:https?|ftp)://[^\s<]+(?:\([^\s<]*\)|[^\s<]))#i';

    /**
     * Maximum display length for URLs before truncation.
     */
    protected const MAX_DISPLAY_LENGTH = 50;

    /**
     * Returns the list of filters provided by this extension.
     *
     * @return TwigFilter[]
     */
    public function getFilters(): array {
        return [
            new TwigFilter('linkify', [$this, 'linkify'], ['is_safe' => ['html']]),
        ];
    }

    /**
     * Converts URLs in text to clickable links.
     *
     * This method:
     * - Escapes HTML entities in the input text for XSS protection
     * - Detects URLs using a regex pattern
     * - Converts URLs to <a> tags with target="_blank" and security attributes
     * - Preserves text formatting and line breaks
     *
     * @param string|null $text The text to process
     *
     * @return string The processed text with URLs converted to links
     */
    public function linkify(?string $text): string {
        $result = '';

        if ($text !== null) {
            // Escape HTML entities for XSS protection
            $escaped_text = htmlspecialchars($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

            // Convert URLs to links
            $result = preg_replace_callback(
                self::URL_PATTERN,
                [$this, 'createLink'],
                $escaped_text
            );

            // Convert newlines to <br> tags (equivalent to nl2br)
            $result = nl2br($result);
        }

        return $result;
    }

    /**
     * Creates an HTML link from a matched URL.
     *
     * @param array<int, string> $matches Regex matches where $matches[1] is the URL
     *
     * @return string The HTML anchor tag
     */
    protected function createLink(array $matches): string {
        $url = $matches[1];

        // Truncate display text for very long URLs (keep full URL in href)
        $display_text = (strlen($url) > self::MAX_DISPLAY_LENGTH)
            ? substr($url, 0, self::MAX_DISPLAY_LENGTH - 3) . '...'
            : $url;

        return sprintf(
            '<a href="%s" target="_blank" rel="noopener noreferrer" class="post-link">%s</a>',
            $url,
            $display_text
        );
    }
}
