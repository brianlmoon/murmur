<?php

declare(strict_types=1);

namespace Murmur\Tests\Unit\Service;

use Murmur\Entity\User;
use Murmur\Repository\SettingMapper;
use Murmur\Repository\UserMapper;
use Murmur\Service\AuthService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for AuthService.
 */
class AuthServiceTest extends TestCase {

    protected AuthService $auth_service;

    protected MockObject $user_mapper;

    protected MockObject $setting_mapper;

    protected function setUp(): void {
        $this->user_mapper = $this->createMock(UserMapper::class);
        $this->setting_mapper = $this->createMock(SettingMapper::class);
        $this->setting_mapper->method('isApprovalRequired')->willReturn(false);
        $this->auth_service = new AuthService($this->user_mapper, $this->setting_mapper);
    }

    public function testRegisterSuccess(): void {
        $this->user_mapper
            ->method('findByUsername')
            ->willReturn(null);

        $this->user_mapper
            ->method('findByEmail')
            ->willReturn(null);

        $this->user_mapper
            ->expects($this->once())
            ->method('save');

        $result = $this->auth_service->register('testuser', 'test@example.com', 'password123', false, 'Test User');

        $this->assertTrue($result['success']);
        $this->assertInstanceOf(User::class, $result['user']);
        $this->assertEquals('testuser', $result['user']->username);
        $this->assertEquals('test@example.com', $result['user']->email);
    }

    public function testRegisterUsernameTooShort(): void {
        $result = $this->auth_service->register('ab', 'test@example.com', 'password123', false, 'Test User');

        $this->assertFalse($result['success']);
        $this->assertEquals('Username must be at least 3 characters.', $result['error']);
    }

    public function testRegisterUsernameTooLong(): void {
        $long_username = str_repeat('a', 31);

        $result = $this->auth_service->register($long_username, 'test@example.com', 'password123', false, 'Test User');

        $this->assertFalse($result['success']);
        $this->assertEquals('Username must be 30 characters or less.', $result['error']);
    }

    public function testRegisterUsernameInvalidCharacters(): void {
        $result = $this->auth_service->register('test-user', 'test@example.com', 'password123', false, 'Test User');

        $this->assertFalse($result['success']);
        $this->assertEquals('Username can only contain letters, numbers, and underscores.', $result['error']);
    }

    public function testRegisterInvalidEmail(): void {
        $result = $this->auth_service->register('testuser', 'invalid-email', 'password123', false, 'Test User');

        $this->assertFalse($result['success']);
        $this->assertEquals('Please enter a valid email address.', $result['error']);
    }

    public function testRegisterPasswordTooShort(): void {
        $result = $this->auth_service->register('testuser', 'test@example.com', 'short', false, 'Test User');

        $this->assertFalse($result['success']);
        $this->assertEquals('Password must be at least 8 characters.', $result['error']);
    }

    public function testRegisterUsernameTaken(): void {
        $existing_user = new User();
        $existing_user->username = 'testuser';

        $this->user_mapper
            ->method('findByUsername')
            ->willReturn($existing_user);

        $result = $this->auth_service->register('testuser', 'test@example.com', 'password123', false, 'Test User');

        $this->assertFalse($result['success']);
        $this->assertEquals('Username is already taken.', $result['error']);
    }

    public function testRegisterEmailTaken(): void {
        $existing_user = new User();
        $existing_user->email = 'test@example.com';

        $this->user_mapper
            ->method('findByUsername')
            ->willReturn(null);

        $this->user_mapper
            ->method('findByEmail')
            ->willReturn($existing_user);

        $result = $this->auth_service->register('testuser', 'test@example.com', 'password123', false, 'Test User');

        $this->assertFalse($result['success']);
        $this->assertEquals('Email is already registered.', $result['error']);
    }

    public function testLoginSuccess(): void {
        $user = new User();
        $user->user_id = 1;
        $user->email = 'test@example.com';
        $user->password_hash = password_hash('password123', PASSWORD_BCRYPT);
        $user->is_disabled = false;

        $this->user_mapper
            ->method('findByEmail')
            ->willReturn($user);

        $result = $this->auth_service->login('test@example.com', 'password123');

        $this->assertTrue($result['success']);
        $this->assertSame($user, $result['user']);
    }

    public function testLoginUserNotFound(): void {
        $this->user_mapper
            ->method('findByEmail')
            ->willReturn(null);

        $result = $this->auth_service->login('test@example.com', 'password123');

        $this->assertFalse($result['success']);
        $this->assertEquals('Invalid email or password.', $result['error']);
    }

    public function testLoginUserDisabled(): void {
        $user = new User();
        $user->email = 'test@example.com';
        $user->password_hash = password_hash('password123', PASSWORD_BCRYPT);
        $user->is_disabled = true;

        $this->user_mapper
            ->method('findByEmail')
            ->willReturn($user);

        $result = $this->auth_service->login('test@example.com', 'password123');

        $this->assertFalse($result['success']);
        $this->assertEquals('This account has been disabled.', $result['error']);
    }

    public function testLoginWrongPassword(): void {
        $user = new User();
        $user->email = 'test@example.com';
        $user->password_hash = password_hash('password123', PASSWORD_BCRYPT);
        $user->is_disabled = false;

        $this->user_mapper
            ->method('findByEmail')
            ->willReturn($user);

        $result = $this->auth_service->login('test@example.com', 'wrongpassword');

        $this->assertFalse($result['success']);
        $this->assertEquals('Invalid email or password.', $result['error']);
    }

    public function testEmailNormalization(): void {
        $this->user_mapper
            ->method('findByUsername')
            ->willReturn(null);

        $this->user_mapper
            ->method('findByEmail')
            ->willReturn(null);

        $this->user_mapper
            ->expects($this->once())
            ->method('save');

        $result = $this->auth_service->register('testuser', '  TEST@EXAMPLE.COM  ', 'password123', false, 'Test User');

        $this->assertTrue($result['success']);
        $this->assertEquals('test@example.com', $result['user']->email);
    }

    public function testUsernameTrimsWhitespace(): void {
        $this->user_mapper
            ->method('findByUsername')
            ->willReturn(null);

        $this->user_mapper
            ->method('findByEmail')
            ->willReturn(null);

        $this->user_mapper
            ->expects($this->once())
            ->method('save');

        $result = $this->auth_service->register('  testuser  ', 'test@example.com', 'password123', false, 'Test User');

        $this->assertTrue($result['success']);
        $this->assertEquals('testuser', $result['user']->username);
    }
}
