<?php

declare(strict_types=1);

namespace Murmur\Service;

use Symfony\Component\Translation\Loader\YamlFileLoader;
use Symfony\Component\Translation\Translator;

/**
 * Service for managing translations and localization.
 *
 * Initializes the Symfony Translator with YAML translation files and provides
 * methods for retrieving the translator instance and available locales.
 */
class TranslationService {

    /**
     * The Symfony Translator instance.
     */
    protected Translator $translator;

    /**
     * The current locale code.
     */
    protected string $locale;

    /**
     * The default locale to use when none is specified.
     */
    protected const DEFAULT_LOCALE = 'en-US';

    /**
     * The translation domain used for message files.
     */
    protected const DOMAIN = 'messages';

    /**
     * Path to the translations directory.
     */
    protected string $translations_path;

    /**
     * Creates a new TranslationService instance.
     *
     * Initializes the Symfony Translator with the YAML loader, loads all
     * available translation files, and sets the current locale. Falls back
     * to en-US if the requested locale is not available.
     *
     * @param string $locale            The locale code (e.g., 'en-US', 'es-MX').
     * @param string $translations_path Path to the translations directory.
     */
    public function __construct(string $locale, string $translations_path) {
        $this->translations_path = $translations_path;
        $this->locale = $this->normalizeLocale($locale);

        $this->translator = new Translator($this->locale);
        $this->translator->addLoader('yaml', new YamlFileLoader());
        $this->translator->setFallbackLocales([self::DEFAULT_LOCALE]);

        $this->loadTranslations();
    }

    /**
     * Returns the configured Translator instance.
     *
     * @return Translator The Symfony Translator.
     */
    public function getTranslator(): Translator {
        return $this->translator;
    }

    /**
     * Returns the current locale code.
     *
     * @return string The locale code (e.g., 'en-US').
     */
    public function getLocale(): string {
        return $this->locale;
    }

    /**
     * Returns a list of available locale codes.
     *
     * Scans the translations directory for YAML files matching the pattern
     * `messages.{locale}.yaml` and extracts the locale codes.
     *
     * @return array<string> Array of locale codes (e.g., ['en-US', 'es-MX']).
     */
    public function getAvailableLocales(): array {
        $locales = [];

        if (is_dir($this->translations_path)) {
            $files = scandir($this->translations_path);

            if ($files !== false) {
                foreach ($files as $file) {
                    if (preg_match('/^messages\.([a-zA-Z-]+)\.yaml$/', $file, $matches)) {
                        $locales[] = $matches[1];
                    }
                }
            }
        }

        sort($locales);

        return $locales;
    }

    /**
     * Returns a map of available locales with their display names.
     *
     * @return array<string, string> Associative array of locale code => display name.
     */
    public function getAvailableLocalesWithNames(): array {
        $locales = $this->getAvailableLocales();
        $result = [];

        // Map of locale codes to display names
        $names = [
            'en-US' => 'English (US)',
            'en-GB' => 'English (UK)',
            'es-ES' => 'Español (España)',
            'es-MX' => 'Español (México)',
            'fr-FR' => 'Français (France)',
            'fr-CA' => 'Français (Canada)',
            'de-DE' => 'Deutsch',
            'it-IT' => 'Italiano',
            'pt-BR' => 'Português (Brasil)',
            'pt-PT' => 'Português (Portugal)',
            'nl-NL' => 'Nederlands',
            'ja-JP' => '日本語',
            'ko-KR' => '한국어',
            'zh-CN' => '中文 (简体)',
            'zh-TW' => '中文 (繁體)',
            'ru-RU' => 'Русский',
            'ar-SA' => 'العربية',
            'pl-PL' => 'Polski',
            'sv-SE' => 'Svenska',
            'da-DK' => 'Dansk',
            'fi-FI' => 'Suomi',
            'nb-NO' => 'Norsk',
            'tr-TR' => 'Türkçe',
            'cs-CZ' => 'Čeština',
            'hu-HU' => 'Magyar',
            'el-GR' => 'Ελληνικά',
            'he-IL' => 'עברית',
            'th-TH' => 'ไทย',
            'vi-VN' => 'Tiếng Việt',
            'id-ID' => 'Bahasa Indonesia',
            'uk-UA' => 'Українська',
        ];

        foreach ($locales as $locale) {
            $result[$locale] = $names[$locale] ?? $locale;
        }

        return $result;
    }

    /**
     * Normalizes the locale code.
     *
     * Returns the default locale if the provided locale is empty or invalid.
     *
     * @param string $locale The locale code to normalize.
     *
     * @return string The normalized locale code.
     */
    protected function normalizeLocale(string $locale): string {
        $result = self::DEFAULT_LOCALE;

        $locale = trim($locale);

        if ($locale !== '' && preg_match('/^[a-zA-Z]{2}(-[a-zA-Z]{2})?$/', $locale)) {
            $result = $locale;
        }

        return $result;
    }

    /**
     * Loads all available translation files into the translator.
     *
     * @return void
     */
    protected function loadTranslations(): void {
        $locales = $this->getAvailableLocales();

        foreach ($locales as $locale) {
            $file = $this->translations_path . '/messages.' . $locale . '.yaml';

            if (file_exists($file)) {
                $this->translator->addResource('yaml', $file, $locale, self::DOMAIN);
            }
        }
    }
}
