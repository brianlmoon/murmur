<?php

declare(strict_types=1);

namespace Murmur\Twig;

use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

/**
 * Twig extension for locale-aware date formatting.
 *
 * Provides a `localized_date` filter that formats dates using format strings
 * defined in the translation files. This allows each locale to define its own
 * preferred date format patterns.
 *
 * Usage in templates:
 *   {{ post.created_at|localized_date('long') }}
 *   {{ post.created_at|localized_date('short') }}
 *   {{ post.created_at|localized_date('datetime') }}
 */
class LocalizedDateExtension extends AbstractExtension {

    /**
     * The translator instance for retrieving format strings.
     */
    protected TranslatorInterface $translator;

    /**
     * Creates a new LocalizedDateExtension instance.
     *
     * @param TranslatorInterface $translator The translator for format strings.
     */
    public function __construct(TranslatorInterface $translator) {
        $this->translator = $translator;
    }

    /**
     * Returns the list of filters provided by this extension.
     *
     * @return TwigFilter[]
     */
    public function getFilters(): array {
        return [
            new TwigFilter('localized_date', [$this, 'formatDate']),
        ];
    }

    /**
     * Formats a date using a named format from the translation file.
     *
     * Retrieves the format string from the `dates.format_{name}` translation key
     * and applies it using PHP's date() function. If the format key doesn't exist,
     * falls back to the default PHP date format.
     *
     * Available formats (defined in translation files):
     *   - short: Abbreviated date (e.g., "Dec 27")
     *   - long: Full date (e.g., "December 27, 2025")
     *   - time: Time only (e.g., "1:39 AM")
     *   - datetime: Full date and time (e.g., "December 27, 2025 1:39 AM")
     *
     * @param string|\DateTimeInterface|null $date   The date to format.
     * @param string                         $format The format name (short, long, time, datetime).
     *
     * @return string The formatted date string, or empty string if date is null.
     */
    public function formatDate(string|\DateTimeInterface|null $date, string $format = 'long'): string {
        $result = '';

        if ($date !== null) {
            $format_key = 'dates.format_' . $format;
            $format_string = $this->translator->trans($format_key);

            // If translation key not found, translator returns the key itself
            // In that case, use a sensible default
            if ($format_string === $format_key) {
                $format_string = $this->getDefaultFormat($format);
            }

            if ($date instanceof \DateTimeInterface) {
                $result = $date->format($format_string);
            } else {
                $timestamp = strtotime($date);

                if ($timestamp !== false) {
                    $result = date($format_string, $timestamp);
                }
            }
        }

        return $result;
    }

    /**
     * Returns the default format string for a given format name.
     *
     * Used as a fallback when the translation key is not found.
     *
     * @param string $format The format name.
     *
     * @return string The default PHP date format string.
     */
    protected function getDefaultFormat(string $format): string {
        $formats = [
            'short'    => 'M j',
            'long'     => 'F j, Y',
            'time'     => 'g:i A',
            'datetime' => 'F j, Y g:i A',
        ];

        return $formats[$format] ?? 'F j, Y';
    }
}
