<?php

declare(strict_types=1);

namespace Murmur\Tests\Unit\Service;

use Murmur\Entity\User;
use Murmur\Entity\UserFollow;
use Murmur\Repository\UserFollowMapper;
use Murmur\Repository\UserMapper;
use Murmur\Service\UserFollowService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for UserFollowService.
 */
class UserFollowServiceTest extends TestCase {

    protected UserFollowService $user_follow_service;

    protected MockObject $user_follow_mapper;

    protected MockObject $user_mapper;

    protected function setUp(): void {
        $this->user_follow_mapper = $this->createMock(UserFollowMapper::class);
        $this->user_mapper = $this->createMock(UserMapper::class);
        $this->user_follow_service = new UserFollowService(
            $this->user_follow_mapper,
            $this->user_mapper
        );
    }

    /**
     * Creates a test user entity.
     *
     * @param int    $user_id     The user ID.
     * @param string $username    The username.
     * @param bool   $is_disabled Whether the user is disabled.
     * @param bool   $is_pending  Whether the user is pending.
     *
     * @return User
     */
    protected function createUser(
        int $user_id,
        string $username = 'testuser',
        bool $is_disabled = false,
        bool $is_pending = false
    ): User {
        $user = new User();
        $user->user_id = $user_id;
        $user->username = $username;
        $user->email = $username . '@example.com';
        $user->is_disabled = $is_disabled;
        $user->is_pending = $is_pending;

        return $user;
    }

    public function testFollowSuccess(): void {
        $follower = $this->createUser(1, 'follower');
        $following = $this->createUser(2, 'following');

        $this->user_mapper
            ->method('load')
            ->with(2)
            ->willReturn($following);

        $this->user_follow_mapper
            ->method('findByFollowerAndFollowing')
            ->with(1, 2)
            ->willReturn(null);

        $this->user_follow_mapper
            ->expects($this->once())
            ->method('save');

        $result = $this->user_follow_service->follow(1, 2);

        $this->assertTrue($result['success']);
        $this->assertArrayNotHasKey('error', $result);
    }

    public function testFollowAlreadyFollowing(): void {
        $following = $this->createUser(2, 'following');
        $existing_follow = new UserFollow();
        $existing_follow->follow_id = 1;
        $existing_follow->follower_id = 1;
        $existing_follow->following_id = 2;

        $this->user_mapper
            ->method('load')
            ->with(2)
            ->willReturn($following);

        $this->user_follow_mapper
            ->method('findByFollowerAndFollowing')
            ->with(1, 2)
            ->willReturn($existing_follow);

        $this->user_follow_mapper
            ->expects($this->never())
            ->method('save');

        $result = $this->user_follow_service->follow(1, 2);

        // Idempotent success
        $this->assertTrue($result['success']);
    }

    public function testFollowYourself(): void {
        $result = $this->user_follow_service->follow(1, 1);

        $this->assertFalse($result['success']);
        $this->assertEquals('You cannot follow yourself.', $result['error']);
    }

    public function testFollowUserNotFound(): void {
        $this->user_mapper
            ->method('load')
            ->with(999)
            ->willReturn(null);

        $result = $this->user_follow_service->follow(1, 999);

        $this->assertFalse($result['success']);
        $this->assertEquals('User not found.', $result['error']);
    }

    public function testFollowDisabledUser(): void {
        $disabled_user = $this->createUser(2, 'disabled', true, false);

        $this->user_mapper
            ->method('load')
            ->with(2)
            ->willReturn($disabled_user);

        $result = $this->user_follow_service->follow(1, 2);

        $this->assertFalse($result['success']);
        $this->assertEquals('This user account is disabled.', $result['error']);
    }

    public function testFollowPendingUser(): void {
        $pending_user = $this->createUser(2, 'pending', false, true);

        $this->user_mapper
            ->method('load')
            ->with(2)
            ->willReturn($pending_user);

        $result = $this->user_follow_service->follow(1, 2);

        $this->assertFalse($result['success']);
        $this->assertEquals('This user account is pending approval.', $result['error']);
    }

    public function testUnfollowSuccess(): void {
        $existing_follow = new UserFollow();
        $existing_follow->follow_id = 1;
        $existing_follow->follower_id = 1;
        $existing_follow->following_id = 2;

        $this->user_follow_mapper
            ->method('findByFollowerAndFollowing')
            ->with(1, 2)
            ->willReturn($existing_follow);

        $this->user_follow_mapper
            ->expects($this->once())
            ->method('delete')
            ->with(1);

        $result = $this->user_follow_service->unfollow(1, 2);

        $this->assertTrue($result['success']);
    }

    public function testUnfollowNotFollowing(): void {
        $this->user_follow_mapper
            ->method('findByFollowerAndFollowing')
            ->with(1, 2)
            ->willReturn(null);

        $this->user_follow_mapper
            ->expects($this->never())
            ->method('delete');

        $result = $this->user_follow_service->unfollow(1, 2);

        // Idempotent success
        $this->assertTrue($result['success']);
    }

    public function testIsFollowingTrue(): void {
        $existing_follow = new UserFollow();
        $existing_follow->follow_id = 1;

        $this->user_follow_mapper
            ->method('findByFollowerAndFollowing')
            ->with(1, 2)
            ->willReturn($existing_follow);

        $result = $this->user_follow_service->isFollowing(1, 2);

        $this->assertTrue($result);
    }

    public function testIsFollowingFalse(): void {
        $this->user_follow_mapper
            ->method('findByFollowerAndFollowing')
            ->with(1, 2)
            ->willReturn(null);

        $result = $this->user_follow_service->isFollowing(1, 2);

        $this->assertFalse($result);
    }

    public function testAreMutualFollowsTrue(): void {
        $this->user_follow_mapper
            ->method('areMutualFollows')
            ->with(1, 2)
            ->willReturn(true);

        $result = $this->user_follow_service->areMutualFollows(1, 2);

        $this->assertTrue($result);
    }

    public function testAreMutualFollowsFalse(): void {
        $this->user_follow_mapper
            ->method('areMutualFollows')
            ->with(1, 2)
            ->willReturn(false);

        $result = $this->user_follow_service->areMutualFollows(1, 2);

        $this->assertFalse($result);
    }

    public function testGetFollowers(): void {
        $user1 = $this->createUser(1, 'follower1');
        $user2 = $this->createUser(2, 'follower2');

        $this->user_follow_mapper
            ->method('getFollowerIds')
            ->with(3)
            ->willReturn([1, 2]);

        $this->user_mapper
            ->method('load')
            ->willReturnMap([
                [1, $user1],
                [2, $user2],
            ]);

        $result = $this->user_follow_service->getFollowers(3);

        $this->assertCount(2, $result);
        $this->assertSame($user1, $result[0]);
        $this->assertSame($user2, $result[1]);
    }

    public function testGetFollowing(): void {
        $user1 = $this->createUser(1, 'following1');
        $user2 = $this->createUser(2, 'following2');

        $this->user_follow_mapper
            ->method('getFollowingIds')
            ->with(3)
            ->willReturn([1, 2]);

        $this->user_mapper
            ->method('load')
            ->willReturnMap([
                [1, $user1],
                [2, $user2],
            ]);

        $result = $this->user_follow_service->getFollowing(3);

        $this->assertCount(2, $result);
        $this->assertSame($user1, $result[0]);
        $this->assertSame($user2, $result[1]);
    }

    public function testGetFollowerCount(): void {
        $this->user_follow_mapper
            ->method('countFollowers')
            ->with(1)
            ->willReturn(42);

        $result = $this->user_follow_service->getFollowerCount(1);

        $this->assertEquals(42, $result);
    }

    public function testGetFollowingCount(): void {
        $this->user_follow_mapper
            ->method('countFollowing')
            ->with(1)
            ->willReturn(15);

        $result = $this->user_follow_service->getFollowingCount(1);

        $this->assertEquals(15, $result);
    }
}
