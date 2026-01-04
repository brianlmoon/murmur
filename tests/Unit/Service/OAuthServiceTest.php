<?php

declare(strict_types=1);

namespace Murmur\Tests\Unit\Service;

use Murmur\Entity\User;
use Murmur\Entity\UserOAuthProvider;
use Murmur\Repository\SettingMapper;
use Murmur\Repository\UserMapper;
use Murmur\Repository\UserOAuthProviderMapper;
use Murmur\Service\OAuthConfigService;
use Murmur\Service\OAuthService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for OAuthService.
 */
class OAuthServiceTest extends TestCase {

    protected OAuthService $service;

    protected MockObject $oauth_config;

    protected MockObject $user_mapper;

    protected MockObject $oauth_mapper;

    protected MockObject $setting_mapper;

    protected function setUp(): void {
        $this->oauth_config = $this->createMock(OAuthConfigService::class);
        $this->user_mapper = $this->createMock(UserMapper::class);
        $this->oauth_mapper = $this->createMock(UserOAuthProviderMapper::class);
        $this->setting_mapper = $this->createMock(SettingMapper::class);

        $this->service = new OAuthService(
            $this->oauth_config,
            $this->user_mapper,
            $this->oauth_mapper,
            $this->setting_mapper
        );
    }

    public function testGenerateUsernameFromName(): void {
        $this->user_mapper
            ->method('findByUsername')
            ->willReturn(null);

        $result = $this->service->generateUsername('John Doe', 'john@example.com');

        $this->assertEquals('johndoe', $result);
    }

    public function testGenerateUsernameFromEmail(): void {
        $this->user_mapper
            ->method('findByUsername')
            ->willReturn(null);

        $result = $this->service->generateUsername(null, 'testuser@example.com');

        $this->assertEquals('testuser', $result);
    }

    public function testGenerateUsernameHandlesCollision(): void {
        $existing_user = new User();
        $existing_user->username = 'johndoe';

        $this->user_mapper
            ->method('findByUsername')
            ->willReturnCallback(function ($username) use ($existing_user) {
                if ($username === 'johndoe') {
                    return $existing_user;
                }

                return null;
            });

        $result = $this->service->generateUsername('John Doe', 'john@example.com');

        $this->assertEquals('johndoe1', $result);
    }

    public function testGenerateUsernameWithInvalidCharacters(): void {
        $this->user_mapper
            ->method('findByUsername')
            ->willReturn(null);

        $result = $this->service->generateUsername('John-Smith@Test', 'john@example.com');

        $this->assertMatchesRegularExpression('/^[a-zA-Z0-9_]+$/', $result);
    }

    public function testGenerateUsernameTruncatesLongNames(): void {
        $this->user_mapper
            ->method('findByUsername')
            ->willReturn(null);

        $long_name = str_repeat('abcdefghij', 10);
        $result = $this->service->generateUsername($long_name, 'test@example.com');

        $this->assertLessThanOrEqual(30, strlen($result));
    }

    public function testGenerateUsernameFallsBackToUser(): void {
        $this->user_mapper
            ->method('findByUsername')
            ->willReturn(null);

        $result = $this->service->generateUsername('', '');

        $this->assertEquals('user', $result);
    }

    public function testCanUnlinkProviderReturnsTrueWithMultipleProviders(): void {
        $user = new User();
        $user->user_id = 1;
        $user->password_hash = 'hashed_password';

        $this->user_mapper
            ->method('load')
            ->with(1)
            ->willReturn($user);

        $provider1 = new UserOAuthProvider();
        $provider1->provider = 'google';

        $provider2 = new UserOAuthProvider();
        $provider2->provider = 'facebook';

        $this->oauth_mapper
            ->method('findByUser')
            ->with(1)
            ->willReturn([$provider1, $provider2]);

        $result = $this->service->canUnlinkProvider(1, 'google');

        $this->assertTrue($result['can_unlink']);
    }

    public function testCanUnlinkProviderReturnsFalseWithLastProviderNoPassword(): void {
        $user = new User();
        $user->user_id = 1;
        $user->password_hash = null;

        $this->user_mapper
            ->method('load')
            ->with(1)
            ->willReturn($user);

        $provider = new UserOAuthProvider();
        $provider->provider = 'google';

        $this->oauth_mapper
            ->method('findByUser')
            ->with(1)
            ->willReturn([$provider]);

        $result = $this->service->canUnlinkProvider(1, 'google');

        $this->assertFalse($result['can_unlink']);
        $this->assertStringContainsString('Cannot unlink last sign-in method', $result['reason']);
    }

    public function testCanUnlinkProviderReturnsTrueWithLastProviderButHasPassword(): void {
        $user = new User();
        $user->user_id = 1;
        $user->password_hash = 'hashed_password';

        $this->user_mapper
            ->method('load')
            ->with(1)
            ->willReturn($user);

        $provider = new UserOAuthProvider();
        $provider->provider = 'google';

        $this->oauth_mapper
            ->method('findByUser')
            ->with(1)
            ->willReturn([$provider]);

        $result = $this->service->canUnlinkProvider(1, 'google');

        $this->assertTrue($result['can_unlink']);
    }

    public function testCreateUserFromOAuthValidatesUsername(): void {
        $oauth_data = [
            'provider_user_id' => '123456',
            'email'            => 'test@example.com',
            'name'             => 'Test User',
        ];

        $result = $this->service->createUserFromOAuth('google', $oauth_data, 'ab');

        $this->assertFalse($result['success']);
        $this->assertEquals('Username must be 3-30 characters.', $result['error']);
    }

    public function testCreateUserFromOAuthValidatesUsernameCharacters(): void {
        $oauth_data = [
            'provider_user_id' => '123456',
            'email'            => 'test@example.com',
            'name'             => 'Test User',
        ];

        $result = $this->service->createUserFromOAuth('google', $oauth_data, 'test-user');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('letters, numbers, and underscores', $result['error']);
    }

    public function testCreateUserFromOAuthChecksUsernameTaken(): void {
        $existing_user = new User();
        $existing_user->username = 'testuser';

        $this->user_mapper
            ->method('findByUsername')
            ->with('testuser')
            ->willReturn($existing_user);

        $oauth_data = [
            'provider_user_id' => '123456',
            'email'            => 'test@example.com',
            'name'             => 'Test User',
        ];

        $result = $this->service->createUserFromOAuth('google', $oauth_data, 'testuser');

        $this->assertFalse($result['success']);
        $this->assertEquals('Username is already taken.', $result['error']);
    }
}
