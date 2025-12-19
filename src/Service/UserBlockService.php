<?php

declare(strict_types=1);

namespace Murmur\Service;

use Murmur\Entity\UserBlock;
use Murmur\Repository\UserBlockMapper;
use Murmur\Repository\UserMapper;

/**
 * Service for user block operations.
 *
 * Handles the business logic for blocking and unblocking users,
 * including validation and block status checks.
 */
class UserBlockService {

    /**
     * The user block mapper for database operations.
     */
    protected UserBlockMapper $user_block_mapper;

    /**
     * The user mapper for user lookups.
     */
    protected UserMapper $user_mapper;

    /**
     * Creates a new UserBlockService instance.
     *
     * @param UserBlockMapper $user_block_mapper The user block mapper.
     * @param UserMapper      $user_mapper       The user mapper.
     */
    public function __construct(UserBlockMapper $user_block_mapper, UserMapper $user_mapper) {
        $this->user_block_mapper = $user_block_mapper;
        $this->user_mapper = $user_mapper;
    }

    /**
     * Blocks a user.
     *
     * @param int $blocker_id The ID of the user doing the blocking.
     * @param int $blocked_id The ID of the user to block.
     *
     * @return array{success: bool, error?: string}
     */
    public function block(int $blocker_id, int $blocked_id): array {
        $result = ['success' => false];

        $validation_error = $this->validateBlock($blocker_id, $blocked_id);

        if ($validation_error !== null) {
            $result['error'] = $validation_error;
        } elseif ($this->isBlocked($blocker_id, $blocked_id)) {
            // Already blocked - idempotent success
            $result['success'] = true;
        } else {
            $block = new UserBlock();
            $block->blocker_id = $blocker_id;
            $block->blocked_id = $blocked_id;

            $this->user_block_mapper->save($block);
            $result['success'] = true;
        }

        return $result;
    }

    /**
     * Unblocks a user.
     *
     * @param int $blocker_id The ID of the user doing the unblocking.
     * @param int $blocked_id The ID of the user to unblock.
     *
     * @return array{success: bool, error?: string}
     */
    public function unblock(int $blocker_id, int $blocked_id): array {
        $result = ['success' => false];

        $block = $this->user_block_mapper->findByUsers($blocker_id, $blocked_id);

        if ($block === null) {
            // Not blocked - idempotent success
            $result['success'] = true;
        } else {
            $this->user_block_mapper->delete($block->block_id);
            $result['success'] = true;
        }

        return $result;
    }

    /**
     * Checks if one user has blocked another.
     *
     * @param int $blocker_id The ID of the potential blocker.
     * @param int $blocked_id The ID of the potentially blocked user.
     *
     * @return bool True if blocker_id has blocked blocked_id.
     */
    public function isBlocked(int $blocker_id, int $blocked_id): bool {
        return $this->user_block_mapper->findByUsers($blocker_id, $blocked_id) !== null;
    }

    /**
     * Checks if either user has blocked the other.
     *
     * Used to determine if messaging should be prevented.
     *
     * @param int $user_id_a First user ID.
     * @param int $user_id_b Second user ID.
     *
     * @return bool True if either user has blocked the other.
     */
    public function hasBlockBetween(int $user_id_a, int $user_id_b): bool {
        return $this->user_block_mapper->hasBlockBetween($user_id_a, $user_id_b);
    }

    /**
     * Retrieves all user IDs that a user has blocked.
     *
     * @param int $user_id The user ID.
     *
     * @return array<int> Array of blocked user IDs.
     */
    public function getBlockedIds(int $user_id): array {
        return $this->user_block_mapper->getBlockedIds($user_id);
    }

    /**
     * Validates a block request.
     *
     * @param int $blocker_id The ID of the user doing the blocking.
     * @param int $blocked_id The ID of the user to block.
     *
     * @return string|null Error message or null if valid.
     */
    protected function validateBlock(int $blocker_id, int $blocked_id): ?string {
        $error = null;

        if ($blocker_id === $blocked_id) {
            $error = 'You cannot block yourself.';
        } else {
            $user = $this->user_mapper->load($blocked_id);

            if ($user === null) {
                $error = 'User not found.';
            }
        }

        return $error;
    }
}
