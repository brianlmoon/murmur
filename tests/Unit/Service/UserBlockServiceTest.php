<?php

declare(strict_types=1);

namespace Murmur\Tests\Unit\Service;

use Murmur\Entity\User;
use Murmur\Entity\UserBlock;
use Murmur\Repository\UserBlockMapper;
use Murmur\Repository\UserMapper;
use Murmur\Service\UserBlockService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for UserBlockService.
 */
class UserBlockServiceTest extends TestCase {

    protected UserBlockService $user_block_service;

    protected MockObject $user_block_mapper;

    protected MockObject $user_mapper;

    protected function setUp(): void {
        $this->user_block_mapper = $this->createMock(UserBlockMapper::class);
        $this->user_mapper = $this->createMock(UserMapper::class);
        $this->user_block_service = new UserBlockService(
            $this->user_block_mapper,
            $this->user_mapper
        );
    }

    /**
     * Creates a test user entity.
     */
    protected function createUser(int $user_id, string $username = 'testuser'): User {
        $user = new User();
        $user->user_id = $user_id;
        $user->username = $username;
        $user->email = $username . '@example.com';

        return $user;
    }

    public function testBlockSuccess(): void {
        $blocked_user = $this->createUser(2, 'blocked');

        $this->user_mapper
            ->method('load')
            ->with(2)
            ->willReturn($blocked_user);

        $this->user_block_mapper
            ->method('findByUsers')
            ->with(1, 2)
            ->willReturn(null);

        $this->user_block_mapper
            ->expects($this->once())
            ->method('save');

        $result = $this->user_block_service->block(1, 2);

        $this->assertTrue($result['success']);
    }

    public function testBlockAlreadyBlocked(): void {
        $blocked_user = $this->createUser(2, 'blocked');
        $existing_block = new UserBlock();
        $existing_block->block_id = 1;

        $this->user_mapper
            ->method('load')
            ->with(2)
            ->willReturn($blocked_user);

        $this->user_block_mapper
            ->method('findByUsers')
            ->with(1, 2)
            ->willReturn($existing_block);

        $this->user_block_mapper
            ->expects($this->never())
            ->method('save');

        $result = $this->user_block_service->block(1, 2);

        // Idempotent success
        $this->assertTrue($result['success']);
    }

    public function testBlockYourself(): void {
        $result = $this->user_block_service->block(1, 1);

        $this->assertFalse($result['success']);
        $this->assertEquals('You cannot block yourself.', $result['error']);
    }

    public function testBlockUserNotFound(): void {
        $this->user_mapper
            ->method('load')
            ->with(999)
            ->willReturn(null);

        $result = $this->user_block_service->block(1, 999);

        $this->assertFalse($result['success']);
        $this->assertEquals('User not found.', $result['error']);
    }

    public function testUnblockSuccess(): void {
        $existing_block = new UserBlock();
        $existing_block->block_id = 1;

        $this->user_block_mapper
            ->method('findByUsers')
            ->with(1, 2)
            ->willReturn($existing_block);

        $this->user_block_mapper
            ->expects($this->once())
            ->method('delete')
            ->with(1);

        $result = $this->user_block_service->unblock(1, 2);

        $this->assertTrue($result['success']);
    }

    public function testUnblockNotBlocked(): void {
        $this->user_block_mapper
            ->method('findByUsers')
            ->with(1, 2)
            ->willReturn(null);

        $this->user_block_mapper
            ->expects($this->never())
            ->method('delete');

        $result = $this->user_block_service->unblock(1, 2);

        // Idempotent success
        $this->assertTrue($result['success']);
    }

    public function testIsBlockedTrue(): void {
        $existing_block = new UserBlock();
        $existing_block->block_id = 1;

        $this->user_block_mapper
            ->method('findByUsers')
            ->with(1, 2)
            ->willReturn($existing_block);

        $result = $this->user_block_service->isBlocked(1, 2);

        $this->assertTrue($result);
    }

    public function testIsBlockedFalse(): void {
        $this->user_block_mapper
            ->method('findByUsers')
            ->with(1, 2)
            ->willReturn(null);

        $result = $this->user_block_service->isBlocked(1, 2);

        $this->assertFalse($result);
    }

    public function testHasBlockBetweenTrue(): void {
        $this->user_block_mapper
            ->method('hasBlockBetween')
            ->with(1, 2)
            ->willReturn(true);

        $result = $this->user_block_service->hasBlockBetween(1, 2);

        $this->assertTrue($result);
    }

    public function testHasBlockBetweenFalse(): void {
        $this->user_block_mapper
            ->method('hasBlockBetween')
            ->with(1, 2)
            ->willReturn(false);

        $result = $this->user_block_service->hasBlockBetween(1, 2);

        $this->assertFalse($result);
    }

    public function testGetBlockedIds(): void {
        $this->user_block_mapper
            ->method('getBlockedIds')
            ->with(1)
            ->willReturn([2, 3, 4]);

        $result = $this->user_block_service->getBlockedIds(1);

        $this->assertEquals([2, 3, 4], $result);
    }
}
