<?php

declare(strict_types=1);

namespace Murmur\Service;

use Murmur\Entity\Like;
use Murmur\Repository\LikeMapper;

/**
 * Service for like operations.
 *
 * Handles liking and unliking posts.
 */
class LikeService {

    /**
     * The like mapper for database operations.
     */
    protected LikeMapper $like_mapper;

    /**
     * Creates a new LikeService instance.
     *
     * @param LikeMapper $like_mapper The like mapper.
     */
    public function __construct(LikeMapper $like_mapper) {
        $this->like_mapper = $like_mapper;
    }

    /**
     * Toggles a like on a post.
     *
     * @param int $user_id The user's ID.
     * @param int $post_id The post's ID.
     *
     * @return array{success: bool, liked: bool}
     */
    public function toggleLike(int $user_id, int $post_id): array {
        $result = ['success' => true, 'liked' => false];

        $existing = $this->like_mapper->findByUserAndPost($user_id, $post_id);

        if ($existing !== null) {
            $this->like_mapper->deleteLike($existing);
            $result['liked'] = false;
        } else {
            $like = new Like();
            $like->user_id = $user_id;
            $like->post_id = $post_id;
            $this->like_mapper->save($like);
            $result['liked'] = true;
        }

        return $result;
    }

    /**
     * Gets the like count for a post.
     *
     * @param int $post_id The post's ID.
     *
     * @return int The like count.
     */
    public function getLikeCount(int $post_id): int {
        return $this->like_mapper->countByPostId($post_id);
    }

    /**
     * Checks if a user has liked a post.
     *
     * @param int $user_id The user's ID.
     * @param int $post_id The post's ID.
     *
     * @return bool True if the user has liked the post.
     */
    public function hasUserLiked(int $user_id, int $post_id): bool {
        $like = $this->like_mapper->findByUserAndPost($user_id, $post_id);

        return $like !== null;
    }

    /**
     * Gets like counts for multiple posts.
     *
     * @param array<int> $post_ids Array of post IDs.
     *
     * @return array<int, int> Map of post_id => like_count.
     */
    public function getLikeCounts(array $post_ids): array {
        return $this->like_mapper->countByPostIds($post_ids);
    }

    /**
     * Gets which posts a user has liked from a list.
     *
     * @param int        $user_id  The user's ID.
     * @param array<int> $post_ids Array of post IDs.
     *
     * @return array<int> Array of post IDs the user has liked.
     */
    public function getUserLikedPostIds(int $user_id, array $post_ids): array {
        return $this->like_mapper->getUserLikedPostIds($user_id, $post_ids);
    }
}
