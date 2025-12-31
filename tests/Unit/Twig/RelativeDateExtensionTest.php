<?php

declare(strict_types=1);

namespace Murmur\Tests\Unit\Twig;

use Murmur\Twig\RelativeDateExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Unit tests for RelativeDateExtension.
 */
class RelativeDateExtensionTest extends TestCase {

    protected RelativeDateExtension $extension;
    protected TranslatorInterface $translator;

    protected function setUp(): void {
        // Create a mock translator that returns English translations
        $this->translator = $this->createMock(TranslatorInterface::class);
        $this->translator->method('trans')->willReturnCallback(function ($key, $params = []) {
            $translations = [
                'relative.just_now' => 'just now',
                'relative.minutes_ago_one' => '1 minute ago',
                'relative.minutes_ago' => '%count% minutes ago',
                'relative.hours_ago_one' => '1 hour ago',
                'relative.hours_ago' => '%count% hours ago',
                'relative.days_ago_one' => '1 day ago',
                'relative.days_ago' => '%count% days ago',
            ];

            $result = $translations[$key] ?? $key;

            // Replace parameters
            foreach ($params as $param_key => $param_value) {
                $result = str_replace($param_key, (string) $param_value, $result);
            }

            return $result;
        });

        $this->extension = new RelativeDateExtension($this->translator);
    }

    /**
     * @dataProvider relativeTimeProvider
     */
    public function testFormatRelativeDateWithinThreshold(int $seconds_ago, string $expected): void {
        $date_string = date('Y-m-d H:i:s', time() - $seconds_ago);
        $result = $this->extension->formatRelativeDate($date_string);
        $this->assertEquals($expected, $result);
    }

    /**
     * Provides test cases for relative time formatting.
     *
     * @return array<string, array{int, string}>
     */
    public static function relativeTimeProvider(): array {
        return [
            'just now (0 seconds)'   => [0, 'just now'],
            'just now (30 seconds)'  => [30, 'just now'],
            'just now (59 seconds)'  => [59, 'just now'],
            '1 minute ago'           => [60, '1 minute ago'],
            '2 minutes ago'          => [120, '2 minutes ago'],
            '59 minutes ago'         => [3540, '59 minutes ago'],
            '1 hour ago'             => [3600, '1 hour ago'],
            '2 hours ago'            => [7200, '2 hours ago'],
            '23 hours ago'           => [82800, '23 hours ago'],
            '1 day ago'              => [86400, '1 day ago'],
            '2 days ago'             => [172800, '2 days ago'],
            '3 days ago'             => [259200, '3 days ago'],
        ];
    }

    public function testFormatRelativeDateBeyondThreshold(): void {
        // 5 days ago (beyond 4-day threshold)
        $date_string = date('Y-m-d H:i:s', time() - 432000);
        $result = $this->extension->formatRelativeDate($date_string);

        // Should return absolute date format
        $expected = date('M j, Y \a\t g:i a', time() - 432000);
        $this->assertEquals($expected, $result);
    }

    public function testFormatRelativeDateExactlyAtThreshold(): void {
        // Exactly 4 days ago (at threshold boundary, should still be relative)
        $date_string = date('Y-m-d H:i:s', time() - 345599);
        $result = $this->extension->formatRelativeDate($date_string);
        $this->assertEquals('3 days ago', $result);
    }

    public function testFormatRelativeDateJustPastThreshold(): void {
        // Just past 4 days (should be absolute)
        $date_string = date('Y-m-d H:i:s', time() - 345600);
        $result = $this->extension->formatRelativeDate($date_string);

        $expected = date('M j, Y \a\t g:i a', time() - 345600);
        $this->assertEquals($expected, $result);
    }

    public function testFormatRelativeDateWithNullReturnsEmptyString(): void {
        $result = $this->extension->formatRelativeDate(null);
        $this->assertEquals('', $result);
    }

    public function testFormatRelativeDateWithCustomFormat(): void {
        // 5 days ago with custom format
        $date_string = date('Y-m-d H:i:s', time() - 432000);
        $result = $this->extension->formatRelativeDate($date_string, 'Y-m-d');

        $expected = date('Y-m-d', time() - 432000);
        $this->assertEquals($expected, $result);
    }

    public function testFormatRelativeDateWithFutureDate(): void {
        // Future date should use absolute format
        $date_string = date('Y-m-d H:i:s', time() + 3600);
        $result = $this->extension->formatRelativeDate($date_string);

        $expected = date('M j, Y \a\t g:i a', time() + 3600);
        $this->assertEquals($expected, $result);
    }

    public function testGetFiltersReturnsRelativeDateFilter(): void {
        $filters = $this->extension->getFilters();

        $this->assertCount(1, $filters);
        $this->assertEquals('relative_date', $filters[0]->getName());
    }
}
