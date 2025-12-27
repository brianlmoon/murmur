<?php

declare(strict_types=1);

namespace Murmur\Service;

use Murmur\Entity\Post;
use Murmur\Entity\PostAttachment;
use Murmur\Entity\User;
use Murmur\Repository\LikeMapper;
use Murmur\Repository\PostAttachmentMapper;
use Murmur\Repository\PostMapper;
use Murmur\Repository\SettingMapper;
use Murmur\Repository\TopicMapper;
use Murmur\Repository\UserMapper;

/**
 * Service for post operations.
 *
 * Handles post creation, retrieval, and deletion. Supports multiple
 * image attachments per post via the PostAttachmentMapper.
 */
class PostService {

    /**
     * The post mapper for database operations.
     */
    protected PostMapper $post_mapper;

    /**
     * The user mapper for database operations.
     */
    protected UserMapper $user_mapper;

    /**
     * The like mapper for database operations.
     */
    protected LikeMapper $like_mapper;

    /**
     * The topic mapper for database operations.
     */
    protected TopicMapper $topic_mapper;

    /**
     * The setting mapper for configuration.
     */
    protected SettingMapper $setting_mapper;

    /**
     * The post attachment mapper for database operations.
     */
    protected PostAttachmentMapper $attachment_mapper;

    /**
     * Creates a new PostService instance.
     *
     * @param PostMapper           $post_mapper       The post mapper.
     * @param UserMapper           $user_mapper       The user mapper.
     * @param LikeMapper           $like_mapper       The like mapper.
     * @param TopicMapper          $topic_mapper      The topic mapper.
     * @param SettingMapper        $setting_mapper    The setting mapper.
     * @param PostAttachmentMapper $attachment_mapper The post attachment mapper.
     */
    public function __construct(
        PostMapper $post_mapper,
        UserMapper $user_mapper,
        LikeMapper $like_mapper,
        TopicMapper $topic_mapper,
        SettingMapper $setting_mapper,
        PostAttachmentMapper $attachment_mapper
    ) {
        $this->post_mapper = $post_mapper;
        $this->user_mapper = $user_mapper;
        $this->like_mapper = $like_mapper;
        $this->topic_mapper = $topic_mapper;
        $this->setting_mapper = $setting_mapper;
        $this->attachment_mapper = $attachment_mapper;
    }

    /**
     * Creates a new post.
     *
     * @param int          $user_id     The author's user ID.
     * @param string       $body        The post content.
     * @param array<string> $image_paths Array of paths to attached images.
     * @param int|null     $topic_id    Optional topic ID for categorization.
     *
     * @return array{success: bool, post?: Post, error?: string}
     */
    public function createPost(int $user_id, string $body, array $image_paths = [], ?int $topic_id = null): array {
        $result = ['success' => false];

        $body = trim($body);
        $validation_error = $this->validatePostBody($body);

        if ($validation_error !== null) {
            $result['error'] = $validation_error;
        } else {
            $post = new Post();
            $post->user_id = $user_id;
            $post->body = $body;
            $post->topic_id = $topic_id;

            $this->post_mapper->save($post);

            // Create attachments for each image path
            $this->createAttachments($post->post_id, $image_paths);

            $result['success'] = true;
            $result['post'] = $post;
        }

        return $result;
    }

    /**
     * Creates a reply to an existing post.
     *
     * @param int           $user_id     The author's user ID.
     * @param int           $parent_id   The parent post ID.
     * @param string        $body        The reply content.
     * @param array<string> $image_paths Array of paths to attached images.
     *
     * @return array{success: bool, post?: Post, error?: string}
     */
    public function createReply(int $user_id, int $parent_id, string $body, array $image_paths = []): array {
        $result = ['success' => false];

        $parent = $this->post_mapper->load($parent_id);

        if ($parent === null) {
            $result['error'] = 'The post you are replying to does not exist.';
        } elseif ($parent->parent_id !== null) {
            // Only allow replies to top-level posts (single-level threading)
            $result['error'] = 'You cannot reply to a reply.';
        } else {
            $body = trim($body);
            $validation_error = $this->validatePostBody($body);

            if ($validation_error !== null) {
                $result['error'] = $validation_error;
            } else {
                $post = new Post();
                $post->user_id = $user_id;
                $post->parent_id = $parent_id;
                $post->body = $body;

                $this->post_mapper->save($post);

                // Create attachments for each image path
                $this->createAttachments($post->post_id, $image_paths);

                $result['success'] = true;
                $result['post'] = $post;
            }
        }

        return $result;
    }

    /**
     * Creates attachment records for a post.
     *
     * @param int           $post_id     The post ID.
     * @param array<string> $image_paths Array of image file paths.
     *
     * @return void
     */
    protected function createAttachments(int $post_id, array $image_paths): void {
        foreach ($image_paths as $sort_order => $file_path) {
            $attachment = new PostAttachment();
            $attachment->post_id = $post_id;
            $attachment->file_path = $file_path;
            $attachment->sort_order = $sort_order;

            $this->attachment_mapper->save($attachment);
        }
    }

    /**
     * Validates post body content.
     *
     * @param string $body The post body to validate.
     *
     * @return string|null Error message or null if valid.
     */
    protected function validatePostBody(string $body): ?string {
        $error = null;
        $max_length = $this->getMaxBodyLength();

        if ($body === '') {
            $error = 'Post cannot be empty.';
        } elseif (mb_strlen($body) > $max_length) {
            $error = 'Post cannot exceed ' . $max_length . ' characters.';
        }

        return $error;
    }

    /**
     * Retrieves posts for the feed with author information and like data.
     *
     * @param int             $limit           Maximum posts to return.
     * @param int             $offset          Number of posts to skip.
     * @param int|null        $current_user_id Current user ID for checking likes.
     * @param array<int>|null $topic_ids       Optional topic IDs to filter by.
     *
     * @return array<array{post: Post, author: User, like_count: int, user_liked: bool, reply_count: int, topic: ?\Murmur\Entity\Topic, attachments: array<PostAttachment>}>
     */
    public function getFeed(int $limit = 50, int $offset = 0, ?int $current_user_id = null, ?array $topic_ids = null): array {
        $result = [];

        if ($topic_ids !== null) {
            $posts = $this->post_mapper->findFeedByTopics($topic_ids, $limit, $offset);
        } else {
            $posts = $this->post_mapper->findFeed($limit, $offset);
        }

        if (empty($posts)) {
            return $result;
        }

        $post_ids = array_map(fn($p) => $p->post_id, $posts);
        $like_counts = $this->like_mapper->countByPostIds($post_ids);
        $reply_counts = $this->post_mapper->countRepliesByPostIds($post_ids);
        $user_liked_ids = $current_user_id !== null
            ? $this->like_mapper->getUserLikedPostIds($current_user_id, $post_ids)
            : [];
        $attachments_by_post = $this->attachment_mapper->findByPostIds($post_ids);

        foreach ($posts as $post) {
            $author = $this->user_mapper->load($post->user_id);

            if ($author !== null) {
                $topic = $post->topic_id !== null ? $this->topic_mapper->load($post->topic_id) : null;

                $result[] = [
                    'post'        => $post,
                    'author'      => $author,
                    'like_count'  => $like_counts[$post->post_id] ?? 0,
                    'user_liked'  => in_array($post->post_id, $user_liked_ids),
                    'reply_count' => $reply_counts[$post->post_id] ?? 0,
                    'topic'       => $topic,
                    'attachments' => $attachments_by_post[$post->post_id] ?? [],
                ];
            }
        }

        return $result;
    }

    /**
     * Retrieves a single post with its author and like data.
     *
     * @param int      $post_id         The post ID.
     * @param int|null $current_user_id Current user ID for checking likes.
     *
     * @return array{post: Post, author: User, like_count: int, user_liked: bool, reply_count: int, topic: ?\Murmur\Entity\Topic, attachments: array<PostAttachment>}|null
     */
    public function getPost(int $post_id, ?int $current_user_id = null): ?array {
        $result = null;

        $post = $this->post_mapper->load($post_id);

        if ($post !== null) {
            $author = $this->user_mapper->load($post->user_id);

            if ($author !== null) {
                $like_count = $this->like_mapper->countByPostId($post_id);
                $user_liked = $current_user_id !== null
                    ? $this->like_mapper->findByUserAndPost($current_user_id, $post_id) !== null
                    : false;
                $reply_counts = $this->post_mapper->countRepliesByPostIds([$post_id]);
                $topic = $post->topic_id !== null ? $this->topic_mapper->load($post->topic_id) : null;
                $attachments = $this->attachment_mapper->findByPostId($post_id);

                $result = [
                    'post'        => $post,
                    'author'      => $author,
                    'like_count'  => $like_count,
                    'user_liked'  => $user_liked,
                    'reply_count' => $reply_counts[$post_id] ?? 0,
                    'topic'       => $topic,
                    'attachments' => $attachments,
                ];
            }
        }

        return $result;
    }

    /**
     * Retrieves replies for a post with author information and like data.
     *
     * @param int      $parent_id       The parent post ID.
     * @param int      $limit           Maximum replies to return.
     * @param int      $offset          Number of replies to skip.
     * @param int|null $current_user_id Current user ID for checking likes.
     * @param string   $order           Sort order: 'ASC' (oldest first) or 'DESC' (newest first).
     *
     * @return array<array{post: Post, author: User, like_count: int, user_liked: bool, attachments: array<PostAttachment>}>
     */
    public function getReplies(int $parent_id, int $limit = 50, int $offset = 0, ?int $current_user_id = null, string $order = 'ASC'): array {
        $result = [];

        $replies = $this->post_mapper->findReplies($parent_id, $limit, $offset, $order);

        if (empty($replies)) {
            return $result;
        }

        $post_ids = array_map(fn($p) => $p->post_id, $replies);
        $like_counts = $this->like_mapper->countByPostIds($post_ids);
        $user_liked_ids = $current_user_id !== null
            ? $this->like_mapper->getUserLikedPostIds($current_user_id, $post_ids)
            : [];
        $attachments_by_post = $this->attachment_mapper->findByPostIds($post_ids);

        foreach ($replies as $reply) {
            $author = $this->user_mapper->load($reply->user_id);

            if ($author !== null) {
                $result[] = [
                    'post'        => $reply,
                    'author'      => $author,
                    'like_count'  => $like_counts[$reply->post_id] ?? 0,
                    'user_liked'  => in_array($reply->post_id, $user_liked_ids),
                    'attachments' => $attachments_by_post[$reply->post_id] ?? [],
                ];
            }
        }

        return $result;
    }

    /**
     * Retrieves posts by a specific user with author information and like data.
     *
     * @param int      $user_id         The user ID.
     * @param int      $limit           Maximum posts to return.
     * @param int      $offset          Number of posts to skip.
     * @param int|null $current_user_id Current user ID for checking likes.
     *
     * @return array<array{post: Post, author: User, like_count: int, user_liked: bool, reply_count: int, topic: ?\Murmur\Entity\Topic, attachments: array<PostAttachment>}>
     */
    public function getPostsByUser(int $user_id, int $limit = 50, int $offset = 0, ?int $current_user_id = null): array {
        $result = [];

        $posts = $this->post_mapper->findByUserId($user_id, $limit, $offset);
        $author = $this->user_mapper->load($user_id);

        if ($author !== null && !empty($posts)) {
            $post_ids = array_map(fn($p) => $p->post_id, $posts);
            $like_counts = $this->like_mapper->countByPostIds($post_ids);
            $reply_counts = $this->post_mapper->countRepliesByPostIds($post_ids);
            $user_liked_ids = $current_user_id !== null
                ? $this->like_mapper->getUserLikedPostIds($current_user_id, $post_ids)
                : [];
            $attachments_by_post = $this->attachment_mapper->findByPostIds($post_ids);

            foreach ($posts as $post) {
                $topic = $post->topic_id !== null ? $this->topic_mapper->load($post->topic_id) : null;

                $result[] = [
                    'post'        => $post,
                    'author'      => $author,
                    'like_count'  => $like_counts[$post->post_id] ?? 0,
                    'user_liked'  => in_array($post->post_id, $user_liked_ids),
                    'reply_count' => $reply_counts[$post->post_id] ?? 0,
                    'topic'       => $topic,
                    'attachments' => $attachments_by_post[$post->post_id] ?? [],
                ];
            }
        }

        return $result;
    }

    /**
     * Deletes a post if the user is authorized.
     *
     * Also deletes all attachment files from storage. Database rows are
     * cleaned up by CASCADE delete constraints.
     *
     * @param int  $post_id  The post ID to delete.
     * @param User $user     The user attempting to delete.
     *
     * @return array{success: bool, error?: string, deleted_files?: array<string>}
     */
    public function deletePost(int $post_id, User $user): array {
        $result = ['success' => false];

        $post = $this->post_mapper->load($post_id);

        if ($post === null) {
            $result['error'] = 'Post not found.';
        } elseif ($post->user_id !== $user->user_id && !$user->is_admin) {
            $result['error'] = 'You do not have permission to delete this post.';
        } else {
            // Collect file paths for cleanup (main post and all replies)
            $deleted_files = [];

            // Get attachment file paths for main post
            $deleted_files = array_merge(
                $deleted_files,
                $this->attachment_mapper->getFilePathsByPostId($post_id)
            );

            // Delete all replies first and collect their attachment paths
            $replies = $this->post_mapper->findReplies($post_id);
            foreach ($replies as $reply) {
                $deleted_files = array_merge(
                    $deleted_files,
                    $this->attachment_mapper->getFilePathsByPostId($reply->post_id)
                );
                $this->post_mapper->delete($reply->post_id);
            }

            // Delete the post (CASCADE will delete attachments table rows)
            $this->post_mapper->delete($post_id);

            $result['success'] = true;
            $result['deleted_files'] = $deleted_files;
        }

        return $result;
    }

    /**
     * Gets the maximum allowed body length from settings.
     *
     * @return int The maximum length.
     */
    public function getMaxBodyLength(): int {
        return $this->setting_mapper->getMaxPostLength();
    }

    /**
     * Gets the maximum allowed attachments per post from settings.
     *
     * @return int The maximum attachment count.
     */
    public function getMaxAttachments(): int {
        return $this->setting_mapper->getMaxAttachments();
    }
}
