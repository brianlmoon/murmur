<?php

declare(strict_types=1);

namespace Murmur\Controller;

use Murmur\Repository\SettingMapper;
use Murmur\Service\ImageService;
use Murmur\Service\LikeService;
use Murmur\Service\LinkPreviewService;
use Murmur\Service\PostService;
use Murmur\Service\SessionService;
use Murmur\Service\TopicService;
use Twig\Environment;

/**
 * Controller for post routes.
 *
 * Handles feed display, post creation, replies, likes, and deletion.
 */
class PostController extends BaseController {

    /**
     * Post service for business logic.
     */
    protected PostService $post_service;

    /**
     * Image service for uploads.
     */
    protected ImageService $image_service;

    /**
     * Like service for like operations.
     */
    protected LikeService $like_service;

    /**
     * Topic service for topic operations.
     */
    protected TopicService $topic_service;

    /**
     * Link preview service for URL previews.
     */
    protected LinkPreviewService $link_preview_service;

    /**
     * Creates a new PostController instance.
     *
     * @param Environment        $twig                 Twig environment for rendering.
     * @param SessionService     $session              Session service.
     * @param SettingMapper      $setting_mapper       Setting mapper.
     * @param PostService        $post_service         Post service.
     * @param ImageService       $image_service        Image service.
     * @param LikeService        $like_service         Like service.
     * @param TopicService       $topic_service        Topic service.
     * @param LinkPreviewService $link_preview_service Link preview service.
     */
    public function __construct(
        Environment $twig,
        SessionService $session,
        SettingMapper $setting_mapper,
        PostService $post_service,
        ImageService $image_service,
        LikeService $like_service,
        TopicService $topic_service,
        LinkPreviewService $link_preview_service
    ) {
        parent::__construct($twig, $session, $setting_mapper);
        $this->post_service = $post_service;
        $this->image_service = $image_service;
        $this->like_service = $like_service;
        $this->topic_service = $topic_service;
        $this->link_preview_service = $link_preview_service;
    }

    /**
     * Displays the home feed.
     *
     * GET /
     *
     * @return string The rendered HTML.
     */
    public function feed(): string {
        // Check if feed is public, redirect to login if not
        if (!$this->setting_mapper->isPublicFeed() && !$this->session->isLoggedIn()) {
            $this->session->addFlash('error', 'Please log in to view posts.');
            $this->redirect('/login');
        }

        $current_user = $this->session->getCurrentUser();
        $current_user_id = $current_user?->user_id;
        $filter = $this->getQuery('filter') ?? 'all';
        $topic_ids = null;
        $followed_topics = [];
        $followed_topic_ids = [];

        if ($filter === 'following' && $current_user_id !== null) {
            $topic_ids = $this->topic_service->getFollowedTopicIds($current_user_id);
            $followed_topics = $this->topic_service->getFollowedTopics($current_user_id);
        }

        if ($current_user_id !== null) {
            $followed_topic_ids = $this->topic_service->getFollowedTopicIds($current_user_id);
        }

        $per_page = 20;
        $page = (int) ($this->getQuery('page') ?? 1);
        if ($page < 1) {
            $page = 1;
        }
        $offset = ($page - 1) * $per_page;

        $posts = $this->post_service->getFeed($per_page + 1, $offset, $current_user_id, $topic_ids);
        $has_more = count($posts) > $per_page;
        if ($has_more) {
            array_pop($posts);
        }

        foreach ($posts as &$post_item) {
            if (isset($post_item['topic']) && $post_item['topic'] !== null) {
                $post_item['user_following'] = in_array($post_item['topic']->topic_id, $followed_topic_ids);
            } else {
                $post_item['user_following'] = false;
            }
        }

        // Fetch link previews for all posts in batch
        $post_bodies = [];

        foreach ($posts as $post_item) {
            $post_bodies[$post_item['post']->post_id] = $post_item['post']->body;
        }

        $previews = $this->link_preview_service->getPreviewsForPosts($post_bodies);

        foreach ($posts as &$post_item) {
            $post_id = $post_item['post']->post_id;
            $post_item['preview'] = $previews[$post_id] ?? null;
        }

        // Enrich posts with image URLs for templates
        $posts = $this->image_service->enrichPostsWithUrls($posts);

        $topics = $this->topic_service->getAllTopics();

        return $this->renderThemed('pages/feed.html.twig', [
            'posts' => $posts,
            'max_length' => $this->post_service->getMaxBodyLength(),
            'topics' => $topics,
            'require_topic' => $this->setting_mapper->isTopicRequired(),
            'filter' => $filter,
            'followed_topics' => $followed_topics,
            'page' => $page,
            'has_more' => $has_more,
        ]);
    }

    /**
     * Displays a single post with its replies.
     *
     * GET /post/{id}
     *
     * @param int $post_id The post ID.
     *
     * @return string The rendered HTML.
     */
    public function show(int $post_id): string {
        $result = '';

        // Check if feed is public, redirect to login if not
        if (!$this->setting_mapper->isPublicFeed() && !$this->session->isLoggedIn()) {
            $this->session->addFlash('error', 'Please log in to view posts.');
            $this->redirect('/login');
        }

        $current_user = $this->session->getCurrentUser();
        $current_user_id = $current_user?->user_id;
        $post_data = $this->post_service->getPost($post_id, $current_user_id);

        if ($post_data === null) {
            http_response_code(404);
            $result = $this->renderThemed('pages/404.html.twig', [
                'message' => 'Post not found.',
            ]);
        } else {
            // If this is a reply, redirect to the parent post
            if ($post_data['post']->parent_id !== null) {
                $this->redirect('/post/' . $post_data['post']->parent_id);
            } else {
                // Get sort preference: query param > cookie > default
                $sort = $this->getQuery('sort');

                if ($sort !== null) {
                    $sort = in_array($sort, ['oldest', 'newest']) ? $sort : 'oldest';
                    // Save preference to cookie (30 days)
                    setcookie('comment_sort', $sort, time() + (30 * 24 * 60 * 60), '/', '', false, true);
                } else {
                    $sort = $_COOKIE['comment_sort'] ?? 'oldest';
                    $sort = in_array($sort, ['oldest', 'newest']) ? $sort : 'oldest';
                }

                $order = $sort === 'newest' ? 'DESC' : 'ASC';

                $replies = $this->post_service->getReplies($post_id, 50, 0, $current_user_id, $order);

                // Fetch link previews for replies
                $reply_bodies = [];

                foreach ($replies as $reply_item) {
                    $reply_bodies[$reply_item['post']->post_id] = $reply_item['post']->body;
                }

                $reply_previews = $this->link_preview_service->getPreviewsForPosts($reply_bodies, true);

                foreach ($replies as &$reply_item) {
                    $reply_id = $reply_item['post']->post_id;
                    $reply_item['preview'] = $reply_previews[$reply_id] ?? null;
                }

                // Enrich replies with image URLs
                $replies = $this->image_service->enrichPostsWithUrls($replies);

                $user_following = false;
                if ($current_user_id !== null && $post_data['topic'] !== null) {
                    $user_following = $this->topic_service->isFollowing($current_user_id, $post_data['topic']->topic_id);
                }

                // Fetch link preview for the post (first URL only)
                $preview = $this->link_preview_service->getPreviewForPost($post_data['post']->body);

                // Generate image URLs for the main post
                $image_url = $post_data['post']->image_path !== null
                    ? $this->image_service->getUrl($post_data['post']->image_path)
                    : null;
                $avatar_url = $post_data['author']->avatar_path !== null
                    ? $this->image_service->getUrl($post_data['author']->avatar_path)
                    : null;

                $result = $this->renderThemed('pages/post.html.twig', [
                    'post'           => $post_data['post'],
                    'author'         => $post_data['author'],
                    'image_url'      => $image_url,
                    'avatar_url'     => $avatar_url,
                    'like_count'     => $post_data['like_count'],
                    'user_liked'     => $post_data['user_liked'],
                    'topic'          => $post_data['topic'],
                    'user_following' => $user_following,
                    'preview'        => $preview,
                    'replies'        => $replies,
                    'max_length'     => $this->post_service->getMaxBodyLength(),
                    'sort'           => $sort,
                ]);
            }
        }

        return $result;
    }

    /**
     * Creates a new post.
     *
     * POST /post
     *
     * @return void
     */
    public function create(): void {
        $this->requireAuth();

        if (!$this->validateCsrf()) {
            $this->session->addFlash('error', 'Invalid form submission. Please try again.');
            $this->redirect('/');
            return;
        }

        $user = $this->session->getCurrentUser();
        $body = (string) $this->getPost('body', '');
        $image_path = null;

        // Handle topic_id
        $topic_id_input = $this->getPost('topic_id');
        $topic_id = ($topic_id_input !== null && $topic_id_input !== '') ? (int) $topic_id_input : null;

        // Handle image upload (only if images are allowed)
        if ($this->setting_mapper->areImagesAllowed()) {
            $file = $_FILES['image'] ?? null;

            if ($this->image_service->hasUpload($file)) {
                $upload_result = $this->image_service->upload($file, 'posts');

                if (!$upload_result['success']) {
                    $this->session->addFlash('error', $upload_result['error']);
                    $this->redirect('/');
                    return;
                }

                $image_path = $upload_result['path'];
            }
        }

        $result = $this->post_service->createPost($user->user_id, $body, $image_path, $topic_id);

        if ($result['success']) {
            $this->session->addFlash('success', 'Post created.');
        } else {
            $this->session->addFlash('error', $result['error']);
        }

        $this->redirect('/');
    }

    /**
     * Creates a reply to a post.
     *
     * POST /post/{id}/reply
     *
     * @param int $post_id The parent post ID.
     *
     * @return void
     */
    public function reply(int $post_id): void {
        $this->requireAuth();

        if (!$this->validateCsrf()) {
            $this->session->addFlash('error', 'Invalid form submission. Please try again.');
            $this->redirect('/post/' . $post_id);
            return;
        }

        $user = $this->session->getCurrentUser();
        $body = (string) $this->getPost('body', '');
        $image_path = null;

        // Handle image upload (only if images are allowed)
        if ($this->setting_mapper->areImagesAllowed()) {
            $file = $_FILES['image'] ?? null;

            if ($this->image_service->hasUpload($file)) {
                $upload_result = $this->image_service->upload($file, 'posts');

                if (!$upload_result['success']) {
                    $this->session->addFlash('error', $upload_result['error']);
                    $this->redirect('/post/' . $post_id);
                    return;
                }

                $image_path = $upload_result['path'];
            }
        }

        $result = $this->post_service->createReply($user->user_id, $post_id, $body, $image_path);

        if ($result['success']) {
            $this->session->addFlash('success', 'Reply posted.');
        } else {
            $this->session->addFlash('error', $result['error']);
        }

        $this->redirect('/post/' . $post_id);
    }

    /**
     * Deletes a post.
     *
     * POST /post/{id}/delete
     *
     * @param int $post_id The post ID to delete.
     *
     * @return void
     */
    public function delete(int $post_id): void {
        $this->requireAuth();

        if (!$this->validateCsrf()) {
            $this->session->addFlash('error', 'Invalid form submission. Please try again.');
            $this->redirect('/');
            return;
        }

        $user = $this->session->getCurrentUser();
        $post_data = $this->post_service->getPost($post_id);
        $redirect_url = '/';

        // If deleting a reply, redirect back to parent post
        if ($post_data !== null && $post_data['post']->parent_id !== null) {
            $redirect_url = '/post/' . $post_data['post']->parent_id;
        }

        $result = $this->post_service->deletePost($post_id, $user);

        if ($result['success']) {
            $this->session->addFlash('success', 'Post deleted.');
        } else {
            $this->session->addFlash('error', $result['error']);
        }

        $this->redirect($redirect_url);
    }

    /**
     * Toggles a like on a post.
     *
     * POST /post/{id}/like
     *
     * @param int $post_id The post ID to like/unlike.
     *
     * @return void
     */
    public function like(int $post_id): void {
        $this->requireAuth();

        $is_xhr = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) 
            && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

        if (!$this->validateCsrf()) {
            if ($is_xhr) {
                header('Content-Type: application/json');
                http_response_code(403);
                echo json_encode(['error' => 'Invalid form submission.']);
                return;
            }
            $this->session->addFlash('error', 'Invalid form submission. Please try again.');
            $this->redirect('/');
            return;
        }

        $user = $this->session->getCurrentUser();
        $result = $this->like_service->toggleLike($user->user_id, $post_id);
        $like_count = $this->like_service->getLikeCount($post_id);

        if ($is_xhr) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'liked' => $result['liked'],
                'like_count' => $like_count,
            ]);
            return;
        }

        // Redirect back to referer or home
        $referer = $_SERVER['HTTP_REFERER'] ?? '/';
        $this->redirect($referer);
    }

    /**
     * Follows a topic.
     *
     * POST /topic/{id}/follow
     *
     * @param int $topic_id The topic ID to follow.
     *
     * @return void
     */
    public function followTopic(int $topic_id): void {
        $this->requireAuth();

        if (!$this->validateCsrf()) {
            $this->session->addFlash('error', 'Invalid form submission. Please try again.');
            $this->redirect('/');
            return;
        }

        $current_user = $this->session->getCurrentUser();
        $result = $this->topic_service->followTopic($current_user->user_id, $topic_id);

        if (!$result['success']) {
            $this->session->addFlash('error', $result['error'] ?? 'Failed to follow topic.');
        } else {
            $this->session->addFlash('success', 'Topic followed successfully.');
        }

        $referer = $_SERVER['HTTP_REFERER'] ?? '/';
        $this->redirect($referer);
    }

    /**
     * Unfollows a topic.
     *
     * POST /topic/{id}/unfollow
     *
     * @param int $topic_id The topic ID to unfollow.
     *
     * @return void
     */
    public function unfollowTopic(int $topic_id): void {
        $this->requireAuth();

        if (!$this->validateCsrf()) {
            $this->session->addFlash('error', 'Invalid form submission. Please try again.');
            $this->redirect('/');
            return;
        }

        $current_user = $this->session->getCurrentUser();
        $result = $this->topic_service->unfollowTopic($current_user->user_id, $topic_id);

        if (!$result['success']) {
            $this->session->addFlash('error', $result['error'] ?? 'Failed to unfollow topic.');
        } else {
            $this->session->addFlash('success', 'Topic unfollowed successfully.');
        }

        $referer = $_SERVER['HTTP_REFERER'] ?? '/';
        $this->redirect($referer);
    }

    /**
     * Displays the topics list page.
     *
     * GET /topics
     *
     * @return string The rendered HTML.
     */
    public function topics(): string {
        $current_user = $this->session->getCurrentUser();
        $current_user_id = $current_user?->user_id;

        $topics = $this->topic_service->getAllTopics();
        $followed_topic_ids = [];

        if ($current_user_id !== null) {
            $followed_topic_ids = $this->topic_service->getFollowedTopicIds($current_user_id);
        }

        return $this->renderThemed('pages/topics.html.twig', [
            'topics'             => $topics,
            'followed_topic_ids' => $followed_topic_ids,
        ]);
    }

    /**
     * Displays posts for a single topic.
     *
     * GET /topic/{id}
     *
     * @param int $topic_id The topic ID.
     *
     * @return string The rendered HTML.
     */
    public function topic(int $topic_id): string {
        $result = '';

        // Check if feed is public, redirect to login if not
        if (!$this->setting_mapper->isPublicFeed() && !$this->session->isLoggedIn()) {
            $this->session->addFlash('error', 'Please log in to view posts.');
            $this->redirect('/login');
        }

        $topic = $this->topic_service->getTopic($topic_id);

        if ($topic === null) {
            http_response_code(404);
            $result = $this->renderThemed('pages/404.html.twig', [
                'message' => 'Topic not found.',
            ]);
        } else {
            $current_user = $this->session->getCurrentUser();
            $current_user_id = $current_user?->user_id;

            $per_page = 20;
            $page = (int) ($this->getQuery('page') ?? 1);
            if ($page < 1) {
                $page = 1;
            }
            $offset = ($page - 1) * $per_page;

            $posts = $this->post_service->getFeed($per_page + 1, $offset, $current_user_id, [$topic_id]);
            $has_more = count($posts) > $per_page;
            if ($has_more) {
                array_pop($posts);
            }

            // Fetch link previews for posts
            $post_bodies = [];
            foreach ($posts as $post_item) {
                $post_bodies[$post_item['post']->post_id] = $post_item['post']->body;
            }
            $previews = $this->link_preview_service->getPreviewsForPosts($post_bodies);

            foreach ($posts as $key => $post_item) {
                $post_id = $post_item['post']->post_id;
                $posts[$key]['preview'] = $previews[$post_id] ?? null;
                $posts[$key]['user_following'] = true;
            }

            // Enrich posts with image URLs
            $posts = $this->image_service->enrichPostsWithUrls($posts);

            $user_following = false;
            if ($current_user_id !== null) {
                $user_following = $this->topic_service->isFollowing($current_user_id, $topic_id);
            }

            // Fetch data for compose form
            $all_topics = $this->topic_service->getAllTopics();
            $max_length = $this->setting_mapper->getMaxPostLength();
            $require_topic = $this->setting_mapper->isTopicRequired();

            $result = $this->renderThemed('pages/topic.html.twig', [
                'topic'          => $topic,
                'posts'          => $posts,
                'user_following' => $user_following,
                'page'           => $page,
                'has_more'       => $has_more,
                'topics'         => $all_topics,
                'max_length'     => $max_length,
                'require_topic'  => $require_topic,
            ]);
        }

        return $result;
    }
}
