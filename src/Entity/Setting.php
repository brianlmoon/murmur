<?php

declare(strict_types=1);

namespace Murmur\Entity;

use Moonspot\ValueObjects\ValueObject;

/**
 * Setting entity representing an instance configuration value.
 *
 * Maps to the `settings` table in the database.
 * Settings are stored as key-value pairs.
 */
class Setting extends ValueObject {

    /**
     * Setting name (primary key).
     */
    public string $setting_name = '';

    /**
     * Setting value.
     */
    public string $setting_value = '';
}
