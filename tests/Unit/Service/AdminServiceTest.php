<?php

declare(strict_types=1);

namespace Murmur\Tests\Unit\Service;

use Murmur\Entity\User;
use Murmur\Repository\PostMapper;
use Murmur\Repository\SettingMapper;
use Murmur\Repository\UserMapper;
use Murmur\Service\AdminService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for AdminService.
 */
class AdminServiceTest extends TestCase {

    protected AdminService $admin_service;

    protected MockObject $user_mapper;

    protected MockObject $post_mapper;

    protected MockObject $setting_mapper;

    protected function setUp(): void {
        $this->user_mapper = $this->createMock(UserMapper::class);
        $this->post_mapper = $this->createMock(PostMapper::class);
        $this->setting_mapper = $this->createMock(SettingMapper::class);
        $this->admin_service = new AdminService(
            $this->user_mapper,
            $this->post_mapper,
            $this->setting_mapper
        );
    }

    public function testDisableUserSuccess(): void {
        $user = new User();
        $user->user_id = 2;
        $user->is_disabled = false;

        $admin = new User();
        $admin->user_id = 1;

        $this->user_mapper
            ->method('load')
            ->willReturn($user);

        $this->user_mapper
            ->expects($this->once())
            ->method('save');

        $result = $this->admin_service->disableUser(2, $admin);

        $this->assertTrue($result['success']);
        $this->assertTrue($user->is_disabled);
    }

    public function testDisableUserNotFound(): void {
        $admin = new User();
        $admin->user_id = 1;

        $this->user_mapper
            ->method('load')
            ->willReturn(null);

        $result = $this->admin_service->disableUser(999, $admin);

        $this->assertFalse($result['success']);
        $this->assertEquals('User not found.', $result['error']);
    }

    public function testDisableSelfNotAllowed(): void {
        $admin = new User();
        $admin->user_id = 1;

        $this->user_mapper
            ->method('load')
            ->willReturn($admin);

        $result = $this->admin_service->disableUser(1, $admin);

        $this->assertFalse($result['success']);
        $this->assertEquals('You cannot disable your own account.', $result['error']);
    }

    public function testEnableUserSuccess(): void {
        $user = new User();
        $user->user_id = 2;
        $user->is_disabled = true;

        $this->user_mapper
            ->method('load')
            ->willReturn($user);

        $this->user_mapper
            ->expects($this->once())
            ->method('save');

        $result = $this->admin_service->enableUser(2);

        $this->assertTrue($result['success']);
        $this->assertFalse($user->is_disabled);
    }

    public function testEnableUserNotFound(): void {
        $this->user_mapper
            ->method('load')
            ->willReturn(null);

        $result = $this->admin_service->enableUser(999);

        $this->assertFalse($result['success']);
        $this->assertEquals('User not found.', $result['error']);
    }

    public function testToggleAdminSuccess(): void {
        $user = new User();
        $user->user_id = 2;
        $user->is_admin = false;

        $admin = new User();
        $admin->user_id = 1;

        $this->user_mapper
            ->method('load')
            ->willReturn($user);

        $this->user_mapper
            ->expects($this->once())
            ->method('save');

        $result = $this->admin_service->toggleAdmin(2, $admin);

        $this->assertTrue($result['success']);
        $this->assertTrue($user->is_admin);
    }

    public function testToggleAdminRemove(): void {
        $user = new User();
        $user->user_id = 2;
        $user->is_admin = true;

        $admin = new User();
        $admin->user_id = 1;

        $this->user_mapper
            ->method('load')
            ->willReturn($user);

        $this->user_mapper
            ->expects($this->once())
            ->method('save');

        $result = $this->admin_service->toggleAdmin(2, $admin);

        $this->assertTrue($result['success']);
        $this->assertFalse($user->is_admin);
    }

    public function testToggleAdminSelfNotAllowed(): void {
        $admin = new User();
        $admin->user_id = 1;
        $admin->is_admin = true;

        $this->user_mapper
            ->method('load')
            ->willReturn($admin);

        $result = $this->admin_service->toggleAdmin(1, $admin);

        $this->assertFalse($result['success']);
        $this->assertEquals('You cannot change your own admin status.', $result['error']);
    }

    public function testToggleAdminUserNotFound(): void {
        $admin = new User();
        $admin->user_id = 1;

        $this->user_mapper
            ->method('load')
            ->willReturn(null);

        $result = $this->admin_service->toggleAdmin(999, $admin);

        $this->assertFalse($result['success']);
        $this->assertEquals('User not found.', $result['error']);
    }

    public function testUpdateSettingsSuccess(): void {
        $this->setting_mapper
            ->expects($this->exactly(11))
            ->method('saveSetting');

        $result = $this->admin_service->updateSettings('My Site', true, true, 'default', 'https://example.com/logo.png', false, true, false, true, 500, 10);

        $this->assertTrue($result['success']);
    }

    public function testUpdateSettingsEmptySiteName(): void {
        $result = $this->admin_service->updateSettings('', true, true, 'default', '');

        $this->assertFalse($result['success']);
        $this->assertEquals('Site name cannot be empty.', $result['error']);
    }

    public function testUpdateSettingsSiteNameTooLong(): void {
        $long_name = str_repeat('a', 51);

        $result = $this->admin_service->updateSettings($long_name, true, true, 'default', '');

        $this->assertFalse($result['success']);
        $this->assertEquals('Site name cannot exceed 50 characters.', $result['error']);
    }

    public function testGetSiteName(): void {
        $this->setting_mapper
            ->method('getSiteName')
            ->willReturn('Test Site');

        $result = $this->admin_service->getSiteName();

        $this->assertEquals('Test Site', $result);
    }

    public function testIsRegistrationOpen(): void {
        $this->setting_mapper
            ->method('isRegistrationOpen')
            ->willReturn(true);

        $result = $this->admin_service->isRegistrationOpen();

        $this->assertTrue($result);
    }

    public function testIsTopicRequired(): void {
        $this->setting_mapper
            ->method('isTopicRequired')
            ->willReturn(true);

        $result = $this->admin_service->isTopicRequired();

        $this->assertTrue($result);
    }
}
