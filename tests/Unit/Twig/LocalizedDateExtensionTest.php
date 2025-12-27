<?php

declare(strict_types=1);

namespace Murmur\Tests\Unit\Twig;

use Murmur\Twig\LocalizedDateExtension;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Unit tests for LocalizedDateExtension.
 */
class LocalizedDateExtensionTest extends TestCase {

    protected LocalizedDateExtension $extension;

    protected MockObject $translator;

    protected function setUp(): void {
        $this->translator = $this->createMock(TranslatorInterface::class);
        $this->extension = new LocalizedDateExtension($this->translator);
    }

    public function testGetFilters(): void {
        $filters = $this->extension->getFilters();

        $this->assertCount(1, $filters);
        $this->assertEquals('localized_date', $filters[0]->getName());
    }

    public function testFormatDateShort(): void {
        $this->translator
            ->method('trans')
            ->with('dates.format_short')
            ->willReturn('M j');

        $result = $this->extension->formatDate('2025-12-27', 'short');

        $this->assertEquals('Dec 27', $result);
    }

    public function testFormatDateLong(): void {
        $this->translator
            ->method('trans')
            ->with('dates.format_long')
            ->willReturn('F j, Y');

        $result = $this->extension->formatDate('2025-12-27', 'long');

        $this->assertEquals('December 27, 2025', $result);
    }

    public function testFormatDateWithDateTimeInterface(): void {
        $this->translator
            ->method('trans')
            ->with('dates.format_long')
            ->willReturn('F j, Y');

        $date = new \DateTime('2025-12-27');
        $result = $this->extension->formatDate($date, 'long');

        $this->assertEquals('December 27, 2025', $result);
    }

    public function testFormatDateNullReturnsEmpty(): void {
        $result = $this->extension->formatDate(null, 'long');

        $this->assertEquals('', $result);
    }

    public function testFormatDateDefaultFormat(): void {
        $this->translator
            ->method('trans')
            ->with('dates.format_long')
            ->willReturn('F j, Y');

        $result = $this->extension->formatDate('2025-12-27');

        $this->assertEquals('December 27, 2025', $result);
    }

    public function testFormatDateFallbackWhenTranslationKeyNotFound(): void {
        // When translator returns the key itself, fallback to default format
        $this->translator
            ->method('trans')
            ->with('dates.format_long')
            ->willReturn('dates.format_long');

        $result = $this->extension->formatDate('2025-12-27', 'long');

        // Should use default 'F j, Y' format
        $this->assertEquals('December 27, 2025', $result);
    }

    public function testFormatTime(): void {
        $this->translator
            ->method('trans')
            ->with('dates.format_time')
            ->willReturn('g:i A');

        $result = $this->extension->formatDate('2025-12-27 14:30:00', 'time');

        $this->assertEquals('2:30 PM', $result);
    }

    public function testFormatDateTime(): void {
        $this->translator
            ->method('trans')
            ->with('dates.format_datetime')
            ->willReturn('F j, Y g:i A');

        $result = $this->extension->formatDate('2025-12-27 14:30:00', 'datetime');

        $this->assertEquals('December 27, 2025 2:30 PM', $result);
    }
}
