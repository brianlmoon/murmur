<?php

declare(strict_types=1);

namespace Murmur\Tests\Unit\Service;

use Murmur\Service\TranslationService;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for TranslationService.
 */
class TranslationServiceTest extends TestCase {

    protected string $translations_path;

    protected function setUp(): void {
        $this->translations_path = dirname(__DIR__, 3) . '/translations';
    }

    public function testDefaultLocale(): void {
        $service = new TranslationService('', $this->translations_path);

        $this->assertEquals('en-US', $service->getLocale());
    }

    public function testCustomLocale(): void {
        $service = new TranslationService('en-US', $this->translations_path);

        $this->assertEquals('en-US', $service->getLocale());
    }

    public function testGetTranslator(): void {
        $service = new TranslationService('en-US', $this->translations_path);
        $translator = $service->getTranslator();

        $this->assertNotNull($translator);
    }

    public function testTranslateSimpleKey(): void {
        $service = new TranslationService('en-US', $this->translations_path);
        $translator = $service->getTranslator();

        $result = $translator->trans('nav.feed');

        $this->assertEquals('Feed', $result);
    }

    public function testTranslateLoginButton(): void {
        $service = new TranslationService('en-US', $this->translations_path);
        $translator = $service->getTranslator();

        $result = $translator->trans('auth.login_button');

        $this->assertEquals('Log in', $result);
    }

    public function testGetAvailableLocales(): void {
        $service = new TranslationService('en-US', $this->translations_path);
        $locales = $service->getAvailableLocales();

        $this->assertContains('en-US', $locales);
    }

    public function testGetAvailableLocalesWithNames(): void {
        $service = new TranslationService('en-US', $this->translations_path);
        $locales = $service->getAvailableLocalesWithNames();

        $this->assertArrayHasKey('en-US', $locales);
        $this->assertEquals('English (US)', $locales['en-US']);
    }

    public function testInvalidLocaleNormalization(): void {
        $service = new TranslationService('invalid', $this->translations_path);

        // Invalid locale format should fall back to en-US
        $this->assertEquals('en-US', $service->getLocale());
    }

    public function testFallbackToEnglish(): void {
        // When locale doesn't have a translation file, falls back to en-US
        $service = new TranslationService('fr-FR', $this->translations_path);
        $translator = $service->getTranslator();

        // Should return English string as fallback
        $result = $translator->trans('auth.login_button');

        $this->assertEquals('Log in', $result);
    }

    public function testDateFormatTranslation(): void {
        $service = new TranslationService('en-US', $this->translations_path);
        $translator = $service->getTranslator();

        $result = $translator->trans('dates.format_long');

        $this->assertEquals('F j, Y', $result);
    }
}
