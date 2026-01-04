<?php

declare(strict_types=1);

namespace Murmur\Tests\Unit\Repository;

use Murmur\Entity\UserOAuthProvider;
use Murmur\Repository\UserOAuthProviderMapper;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for UserOAuthProviderMapper.
 */
class UserOAuthProviderMapperTest extends TestCase {

    public function testMapperConstants(): void {
        $this->assertEquals('murmur', UserOAuthProviderMapper::DATABASE_NAME);
        $this->assertEquals('user_oauth_providers', UserOAuthProviderMapper::TABLE);
        $this->assertEquals('oauth_id', UserOAuthProviderMapper::PRIMARY_KEY);
        $this->assertEquals(UserOAuthProvider::class, UserOAuthProviderMapper::MAPPED_CLASS);
    }

    public function testMappingContainsRequiredFields(): void {
        $required_fields = [
            'oauth_id',
            'user_id',
            'provider',
            'provider_user_id',
            'email',
            'name',
            'created_at',
            'updated_at',
        ];

        foreach ($required_fields as $field) {
            $this->assertArrayHasKey(
                $field,
                UserOAuthProviderMapper::MAPPING,
                "Mapping should contain field: {$field}"
            );
        }
    }

    public function testTimestampsAreReadOnly(): void {
        $this->assertArrayHasKey('read_only', UserOAuthProviderMapper::MAPPING['created_at']);
        $this->assertTrue(UserOAuthProviderMapper::MAPPING['created_at']['read_only']);

        $this->assertArrayHasKey('read_only', UserOAuthProviderMapper::MAPPING['updated_at']);
        $this->assertTrue(UserOAuthProviderMapper::MAPPING['updated_at']['read_only']);
    }
}
