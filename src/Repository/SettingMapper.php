<?php

declare(strict_types=1);

namespace Murmur\Repository;

use DealNews\DB\AbstractMapper;
use Murmur\Entity\Setting;

/**
 * Data Mapper for the Setting entity.
 *
 * Handles persistence operations for the `settings` table.
 */
class SettingMapper extends AbstractMapper {

    /**
     * Database configuration name.
     */
    public const DATABASE_NAME = 'murmur';

    /**
     * Database table name.
     */
    public const TABLE = 'settings';

    /**
     * Primary key column name.
     */
    public const PRIMARY_KEY = 'setting_name';

    /**
     * Value object class for this mapper.
     */
    public const MAPPED_CLASS = Setting::class;

    /**
     * Column mappings from database to entity properties.
     *
     * @var array<string, array>
     */
    public const MAPPING = [
        'setting_name'  => [],
        'setting_value' => [],
    ];

    /**
     * Retrieves a setting value by name.
     *
     * @param string      $name    The setting name.
     * @param string|null $default Default value if setting doesn't exist.
     *
     * @return string|null The setting value or default.
     */
    public function getSetting(string $name, ?string $default = null): ?string {
        $result = $default;

        $setting = $this->load($name);

        if ($setting instanceof Setting) {
            $result = $setting->setting_value;
        }

        return $result;
    }

    /**
     * Sets a setting value, creating or updating as needed.
     *
     * Uses direct SQL to handle string primary keys properly.
     *
     * @param string $name  The setting name.
     * @param string $value The setting value.
     *
     * @return bool True on success.
     */
    public function saveSetting(string $name, string $value): bool {
        $existing = $this->load($name);

        if ($existing === null) {
            $success = $this->crud->create(self::TABLE, [
                'setting_name'  => $name,
                'setting_value' => $value,
            ]);
        } else {
            $success = $this->crud->update(
                self::TABLE,
                ['setting_value' => $value],
                ['setting_name' => $name]
            );
        }

        return $success;
    }

    /**
     * Retrieves the site name setting.
     *
     * @return string The site name.
     */
    public function getSiteName(): string {
        return $this->getSetting('site_name', 'Murmur') ?? 'Murmur';
    }

    /**
     * Checks if registration is currently open.
     *
     * @return bool True if registration is open.
     */
    public function isRegistrationOpen(): bool {
        return $this->getSetting('registration_open', '1') === '1';
    }

    /**
     * Checks if image uploads are allowed.
     *
     * @return bool True if images are allowed.
     */
    public function areImagesAllowed(): bool {
        return $this->getSetting('images_allowed', '1') === '1';
    }

    /**
     * Retrieves the current theme name.
     *
     * @return string The theme name.
     */
    public function getTheme(): string {
        return $this->getSetting('theme', 'default') ?? 'default';
    }

    /**
     * Retrieves the logo URL.
     *
     * @return string The logo URL or empty string if not set.
     */
    public function getLogoUrl(): string {
        return $this->getSetting('logo_url', '') ?? '';
    }

    /**
     * Checks if admin approval is required for new accounts.
     *
     * @return bool True if approval is required.
     */
    public function isApprovalRequired(): bool {
        return $this->getSetting('require_approval', '0') === '1';
    }

    /**
     * Retrieves the base URL for subdirectory installations.
     *
     * @return string The base URL (e.g., '/murmur') or empty string for root.
     */
    public function getBaseUrl(): string {
        return $this->getSetting('base_url', '') ?? '';
    }

    /**
     * Checks if the feed is publicly viewable.
     *
     * @return bool True if non-logged-in users can view posts.
     */
    public function isPublicFeed(): bool {
        return $this->getSetting('public_feed', '1') === '1';
    }

    /**
     * Checks if a topic is required when creating posts.
     *
     * @return bool True if topic selection is required.
     */
    public function isTopicRequired(): bool {
        return $this->getSetting('require_topic', '0') === '1';
    }

    /**
     * Checks if private messaging is enabled.
     *
     * @return bool True if messaging is enabled.
     */
    public function isMessagingEnabled(): bool {
        return $this->getSetting('messaging_enabled', '1') === '1';
    }

    /**
     * Retrieves the maximum post body length.
     *
     * @return int The maximum length in characters.
     */
    public function getMaxPostLength(): int {
        $value = $this->getSetting('max_post_length', '500');

        return (int) ($value ?? 500);
    }
}
