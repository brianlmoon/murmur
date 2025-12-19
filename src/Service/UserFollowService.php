<?php

declare(strict_types=1);

namespace Murmur\Service;

use Murmur\Entity\User;
use Murmur\Entity\UserFollow;
use Murmur\Repository\UserFollowMapper;
use Murmur\Repository\UserMapper;

/**
 * Service for user follow operations.
 *
 * Handles the business logic for following and unfollowing users,
 * including validation and mutual follow detection.
 */
class UserFollowService {

    /**
     * The user follow mapper for database operations.
     */
    protected UserFollowMapper $user_follow_mapper;

    /**
     * The user mapper for user lookups.
     */
    protected UserMapper $user_mapper;

    /**
     * Creates a new UserFollowService instance.
     *
     * @param UserFollowMapper $user_follow_mapper The user follow mapper.
     * @param UserMapper       $user_mapper        The user mapper.
     */
    public function __construct(UserFollowMapper $user_follow_mapper, UserMapper $user_mapper) {
        $this->user_follow_mapper = $user_follow_mapper;
        $this->user_mapper = $user_mapper;
    }

    /**
     * Follows a user.
     *
     * @param int $follower_id  The ID of the user doing the following.
     * @param int $following_id The ID of the user to follow.
     *
     * @return array{success: bool, error?: string}
     */
    public function follow(int $follower_id, int $following_id): array {
        $result = ['success' => false];

        $validation_error = $this->validateFollow($follower_id, $following_id);

        if ($validation_error !== null) {
            $result['error'] = $validation_error;
        } elseif ($this->isFollowing($follower_id, $following_id)) {
            // Already following - idempotent success
            $result['success'] = true;
        } else {
            $follow = new UserFollow();
            $follow->follower_id = $follower_id;
            $follow->following_id = $following_id;

            $this->user_follow_mapper->save($follow);
            $result['success'] = true;
        }

        return $result;
    }

    /**
     * Unfollows a user.
     *
     * @param int $follower_id  The ID of the user doing the unfollowing.
     * @param int $following_id The ID of the user to unfollow.
     *
     * @return array{success: bool, error?: string}
     */
    public function unfollow(int $follower_id, int $following_id): array {
        $result = ['success' => false];

        $follow = $this->user_follow_mapper->findByFollowerAndFollowing($follower_id, $following_id);

        if ($follow === null) {
            // Not following - idempotent success
            $result['success'] = true;
        } else {
            $this->user_follow_mapper->delete($follow->follow_id);
            $result['success'] = true;
        }

        return $result;
    }

    /**
     * Checks if one user is following another.
     *
     * @param int $follower_id  The ID of the potential follower.
     * @param int $following_id The ID of the user potentially being followed.
     *
     * @return bool True if follower_id is following following_id.
     */
    public function isFollowing(int $follower_id, int $following_id): bool {
        return $this->user_follow_mapper->findByFollowerAndFollowing($follower_id, $following_id) !== null;
    }

    /**
     * Checks if two users are mutual follows (both follow each other).
     *
     * @param int $user_id_a First user ID.
     * @param int $user_id_b Second user ID.
     *
     * @return bool True if both users follow each other.
     */
    public function areMutualFollows(int $user_id_a, int $user_id_b): bool {
        return $this->user_follow_mapper->areMutualFollows($user_id_a, $user_id_b);
    }

    /**
     * Gets all users who follow a given user.
     *
     * @param int $user_id The ID of the user to get followers for.
     *
     * @return array<User> Array of User entities.
     */
    public function getFollowers(int $user_id): array {
        $result = [];

        $follower_ids = $this->user_follow_mapper->getFollowerIds($user_id);

        foreach ($follower_ids as $follower_id) {
            $user = $this->user_mapper->load($follower_id);
            if ($user !== null) {
                $result[] = $user;
            }
        }

        return $result;
    }

    /**
     * Gets all users that a given user is following.
     *
     * @param int $user_id The ID of the user to get following for.
     *
     * @return array<User> Array of User entities.
     */
    public function getFollowing(int $user_id): array {
        $result = [];

        $following_ids = $this->user_follow_mapper->getFollowingIds($user_id);

        foreach ($following_ids as $following_id) {
            $user = $this->user_mapper->load($following_id);
            if ($user !== null) {
                $result[] = $user;
            }
        }

        return $result;
    }

    /**
     * Gets the follower count for a user.
     *
     * @param int $user_id The ID of the user.
     *
     * @return int The number of followers.
     */
    public function getFollowerCount(int $user_id): int {
        return $this->user_follow_mapper->countFollowers($user_id);
    }

    /**
     * Gets the following count for a user.
     *
     * @param int $user_id The ID of the user.
     *
     * @return int The number of users being followed.
     */
    public function getFollowingCount(int $user_id): int {
        return $this->user_follow_mapper->countFollowing($user_id);
    }

    /**
     * Validates a follow request.
     *
     * @param int $follower_id  The ID of the user doing the following.
     * @param int $following_id The ID of the user to follow.
     *
     * @return string|null Error message or null if valid.
     */
    protected function validateFollow(int $follower_id, int $following_id): ?string {
        $error = null;

        if ($follower_id === $following_id) {
            $error = 'You cannot follow yourself.';
        } else {
            $user = $this->user_mapper->load($following_id);

            if ($user === null) {
                $error = 'User not found.';
            } elseif ($user->is_disabled) {
                $error = 'This user account is disabled.';
            } elseif ($user->is_pending) {
                $error = 'This user account is pending approval.';
            }
        }

        return $error;
    }
}
