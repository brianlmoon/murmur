<?php

declare(strict_types=1);

namespace Murmur\Twig;

use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

/**
 * Twig extension for displaying relative dates.
 *
 * Provides a `relative_date` filter that shows human-readable relative times
 * (e.g., "2 hours ago", "3 days ago") for recent dates, falling back to an
 * absolute date format for older dates.
 */
class RelativeDateExtension extends AbstractExtension {

    /**
     * Threshold in seconds for showing relative dates (4 days).
     */
    protected const THRESHOLD_SECONDS = 345600;

    /**
     * Default date format for absolute dates.
     */
    protected const DEFAULT_FORMAT = 'M j, Y \a\t g:i a';

    /**
     * The translator instance for retrieving relative date strings.
     */
    protected TranslatorInterface $translator;

    /**
     * Creates a new RelativeDateExtension instance.
     *
     * @param TranslatorInterface $translator The translator for relative date strings.
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
            new TwigFilter('relative_date', [$this, 'formatRelativeDate']),
        ];
    }

    /**
     * Formats a date as a relative time string if within threshold, otherwise absolute.
     *
     * @param string|null $date_string The date string to format (e.g., "2025-12-14 10:30:00")
     * @param string      $format      The absolute date format to use if beyond threshold
     *
     * @return string The formatted date string
     */
    public function formatRelativeDate(?string $date_string, string $format = self::DEFAULT_FORMAT): string {
        $result = '';

        if ($date_string !== null) {
            $timestamp = strtotime($date_string);

            if ($timestamp !== false) {
                $now = time();
                $diff = $now - $timestamp;

                if ($diff >= 0 && $diff < self::THRESHOLD_SECONDS) {
                    $result = $this->getRelativeTimeString($diff);
                } else {
                    $result = date($format, $timestamp);
                }
            }
        }

        return $result;
    }

    /**
     * Converts a time difference in seconds to a human-readable string.
     *
     * @param int $seconds The number of seconds elapsed
     *
     * @return string The relative time string (e.g., "2 hours ago")
     */
    protected function getRelativeTimeString(int $seconds): string {
        $result = '';

        if ($seconds < 60) {
            $result = $this->translator->trans('relative.just_now');
        } elseif ($seconds < 3600) {
            $minutes = (int) floor($seconds / 60);
            $result = $minutes === 1
                ? $this->translator->trans('relative.minutes_ago_one')
                : $this->translator->trans('relative.minutes_ago', ['%count%' => $minutes]);
        } elseif ($seconds < 86400) {
            $hours = (int) floor($seconds / 3600);
            $result = $hours === 1
                ? $this->translator->trans('relative.hours_ago_one')
                : $this->translator->trans('relative.hours_ago', ['%count%' => $hours]);
        } else {
            $days = (int) floor($seconds / 86400);
            $result = $days === 1
                ? $this->translator->trans('relative.days_ago_one')
                : $this->translator->trans('relative.days_ago', ['%count%' => $days]);
        }

        return $result;
    }
}
