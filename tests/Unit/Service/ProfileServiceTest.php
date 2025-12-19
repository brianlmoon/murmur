<?php

declare(strict_types=1);

namespace Murmur\Tests\Unit\Service;

use Murmur\Entity\User;
use Murmur\Repository\UserMapper;
use Murmur\Service\ProfileService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for ProfileService.
 */
class ProfileServiceTest extends TestCase {

    protected ProfileService $profile_service;

    protected MockObject $user_mapper;

    protected function setUp(): void {
        $this->user_mapper = $this->createMock(UserMapper::class);
        $this->profile_service = new ProfileService($this->user_mapper);
    }

    public function testGetByUsername(): void {
        $user = new User();
        $user->username = 'testuser';

        $this->user_mapper
            ->method('findByUsername')
            ->with('testuser')
            ->willReturn($user);

        $result = $this->profile_service->getByUsername('testuser');

        $this->assertSame($user, $result);
    }

    public function testGetByUsernameNotFound(): void {
        $this->user_mapper
            ->method('findByUsername')
            ->willReturn(null);

        $result = $this->profile_service->getByUsername('nonexistent');

        $this->assertNull($result);
    }

    public function testUpdateProfileSuccess(): void {
        $user = new User();
        $user->user_id = 1;
        $user->username = 'oldname';
        $user->email = 'old@example.com';

        $this->user_mapper
            ->method('findByUsername')
            ->willReturn(null);

        $this->user_mapper
            ->method('findByEmail')
            ->willReturn(null);

        $this->user_mapper
            ->expects($this->once())
            ->method('save');

        $result = $this->profile_service->updateProfile(
            $user,
            'newname',
            'new@example.com',
            'New bio',
            null,
            'Test User'
        );

        $this->assertTrue($result['success']);
        $this->assertEquals('newname', $user->username);
        $this->assertEquals('new@example.com', $user->email);
        $this->assertEquals('New bio', $user->bio);
        $this->assertEquals('Test User', $user->name);
    }

    public function testUpdateProfileWithAvatar(): void {
        $user = new User();
        $user->user_id = 1;
        $user->username = 'testuser';
        $user->email = 'test@example.com';

        $this->user_mapper
            ->method('findByUsername')
            ->willReturn(null);

        $this->user_mapper
            ->method('findByEmail')
            ->willReturn(null);

        $this->user_mapper
            ->expects($this->once())
            ->method('save');

        $result = $this->profile_service->updateProfile(
            $user,
            'testuser',
            'test@example.com',
            null,
            'avatars/new.jpg',
            'Test User'
        );

        $this->assertTrue($result['success']);
        $this->assertEquals('avatars/new.jpg', $user->avatar_path);
    }

    public function testUpdateProfileUsernameTaken(): void {
        $user = new User();
        $user->user_id = 1;
        $user->username = 'oldname';

        $other_user = new User();
        $other_user->user_id = 2;
        $other_user->username = 'newname';

        $this->user_mapper
            ->method('findByUsername')
            ->willReturn($other_user);

        $result = $this->profile_service->updateProfile(
            $user,
            'newname',
            'test@example.com',
            null,
            null,
            'Test User'
        );

        $this->assertFalse($result['success']);
        $this->assertEquals('Username is already taken.', $result['error']);
    }

    public function testUpdateProfileEmailTaken(): void {
        $user = new User();
        $user->user_id = 1;
        $user->email = 'old@example.com';

        $other_user = new User();
        $other_user->user_id = 2;
        $other_user->email = 'new@example.com';

        $this->user_mapper
            ->method('findByUsername')
            ->willReturn(null);

        $this->user_mapper
            ->method('findByEmail')
            ->willReturn($other_user);

        $result = $this->profile_service->updateProfile(
            $user,
            'testuser',
            'new@example.com',
            null,
            null,
            'Test User'
        );

        $this->assertFalse($result['success']);
        $this->assertEquals('Email is already in use.', $result['error']);
    }

    public function testUpdateProfileSameUsernameAllowed(): void {
        $user = new User();
        $user->user_id = 1;
        $user->username = 'testuser';
        $user->email = 'test@example.com';

        // Returns the same user (which is allowed)
        $this->user_mapper
            ->method('findByUsername')
            ->willReturn($user);

        $this->user_mapper
            ->method('findByEmail')
            ->willReturn($user);

        $this->user_mapper
            ->expects($this->once())
            ->method('save');

        $result = $this->profile_service->updateProfile(
            $user,
            'testuser',
            'test@example.com',
            'Updated bio',
            null,
            'Test User'
        );

        $this->assertTrue($result['success']);
    }

    public function testUpdateProfileUsernameTooShort(): void {
        $user = new User();
        $user->user_id = 1;

        $result = $this->profile_service->updateProfile(
            $user,
            'ab',
            'test@example.com',
            null,
            null,
            'Test User'
        );

        $this->assertFalse($result['success']);
        $this->assertEquals('Username must be at least 3 characters.', $result['error']);
    }

    public function testUpdateProfileBioTooLong(): void {
        $user = new User();
        $user->user_id = 1;

        $long_bio = str_repeat('a', 161);

        $result = $this->profile_service->updateProfile(
            $user,
            'testuser',
            'test@example.com',
            $long_bio,
            null,
            'Test User'
        );

        $this->assertFalse($result['success']);
        $this->assertEquals('Bio cannot exceed 160 characters.', $result['error']);
    }

    public function testRemoveAvatar(): void {
        $user = new User();
        $user->user_id = 1;
        $user->avatar_path = 'avatars/old.jpg';

        $this->user_mapper
            ->expects($this->once())
            ->method('save');

        $old_path = $this->profile_service->removeAvatar($user);

        $this->assertEquals('avatars/old.jpg', $old_path);
        $this->assertNull($user->avatar_path);
    }

    public function testUpdatePasswordSuccess(): void {
        $user = new User();
        $user->user_id = 1;
        $user->password_hash = password_hash('oldpassword', PASSWORD_BCRYPT);

        $this->user_mapper
            ->expects($this->once())
            ->method('save');

        $result = $this->profile_service->updatePassword($user, 'oldpassword', 'newpassword123');

        $this->assertTrue($result['success']);
        $this->assertTrue(password_verify('newpassword123', $user->password_hash));
    }

    public function testUpdatePasswordWrongCurrent(): void {
        $user = new User();
        $user->user_id = 1;
        $user->password_hash = password_hash('oldpassword', PASSWORD_BCRYPT);

        $result = $this->profile_service->updatePassword($user, 'wrongpassword', 'newpassword123');

        $this->assertFalse($result['success']);
        $this->assertEquals('Current password is incorrect.', $result['error']);
    }

    public function testUpdatePasswordTooShort(): void {
        $user = new User();
        $user->user_id = 1;
        $user->password_hash = password_hash('oldpassword', PASSWORD_BCRYPT);

        $result = $this->profile_service->updatePassword($user, 'oldpassword', 'short');

        $this->assertFalse($result['success']);
        $this->assertEquals('New password must be at least 8 characters.', $result['error']);
    }

    public function testGetMaxBioLength(): void {
        $this->assertEquals(160, $this->profile_service->getMaxBioLength());
    }
}
