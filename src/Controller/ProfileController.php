<?php

declare(strict_types=1);

namespace Murmur\Controller;

use Murmur\Repository\SettingMapper;
use Murmur\Service\ImageService;
use Murmur\Service\MessageService;
use Murmur\Service\PostService;
use Murmur\Service\ProfileService;
use Murmur\Service\SessionService;
use Murmur\Service\UserFollowService;
use Twig\Environment;

/**
 * Controller for user profile routes.
 *
 * Handles viewing and editing user profiles, and follow/unfollow actions.
 */
class ProfileController extends BaseController {

    /**
     * Profile service for business logic.
     */
    protected ProfileService $profile_service;

    /**
     * Post service for user posts.
     */
    protected PostService $post_service;

    /**
     * Image service for avatar uploads.
     */
    protected ImageService $image_service;

    /**
     * User follow service for follow operations.
     */
    protected UserFollowService $user_follow_service;

    /**
     * Message service for messaging checks.
     */
    protected MessageService $message_service;

    /**
     * Creates a new ProfileController instance.
     *
     * @param Environment       $twig                Twig environment for rendering.
     * @param SessionService    $session             Session service.
     * @param SettingMapper     $setting_mapper      Setting mapper.
     * @param ProfileService    $profile_service     Profile service.
     * @param PostService       $post_service        Post service.
     * @param ImageService      $image_service       Image service.
     * @param UserFollowService $user_follow_service User follow service.
     * @param MessageService    $message_service     Message service.
     */
    public function __construct(
        Environment $twig,
        SessionService $session,
        SettingMapper $setting_mapper,
        ProfileService $profile_service,
        PostService $post_service,
        ImageService $image_service,
        UserFollowService $user_follow_service,
        MessageService $message_service
    ) {
        parent::__construct($twig, $session, $setting_mapper);
        $this->profile_service = $profile_service;
        $this->post_service = $post_service;
        $this->image_service = $image_service;
        $this->user_follow_service = $user_follow_service;
        $this->message_service = $message_service;
    }

    /**
     * Displays a user's public profile.
     *
     * GET /user/{username}
     *
     * @param string $username The username to view.
     *
     * @return string The rendered HTML.
     */
    public function show(string $username): string {
        $result = '';

        // Check if feed is public, redirect to login if not
        if (!$this->setting_mapper->isPublicFeed() && !$this->session->isLoggedIn()) {
            $this->session->addFlash('error', 'Please log in to view profiles.');
            $this->redirect('/login');
        }

        $user = $this->profile_service->getByUsername($username);

        if ($user === null || $user->is_disabled) {
            http_response_code(404);
            $result = $this->renderThemed('pages/404.html.twig', [
                'message' => 'User not found.',
            ]);
        } else {
            $current_user = $this->session->getCurrentUser();
            $current_user_id = $current_user?->user_id;
            $posts = $this->post_service->getPostsByUser($user->user_id, 50, 0, $current_user_id);

            // Enrich posts with image URLs
            $posts = $this->image_service->enrichPostsWithUrls($posts);

            // Get follow information
            $is_following = false;
            $can_message = false;
            $follower_count = $this->user_follow_service->getFollowerCount($user->user_id);
            $following_count = $this->user_follow_service->getFollowingCount($user->user_id);

            if ($current_user !== null && $current_user->user_id !== $user->user_id) {
                $is_following = $this->user_follow_service->isFollowing(
                    $current_user->user_id,
                    $user->user_id
                );

                $can_message_result = $this->message_service->canMessage(
                    $current_user->user_id,
                    $user->user_id
                );
                $can_message = $can_message_result['can_message'];
            }

            // Generate profile avatar URL
            $profile_avatar_url = $user->avatar_path !== null
                ? $this->image_service->getUrl($user->avatar_path)
                : null;

            $result = $this->renderThemed('pages/profile.html.twig', [
                'profile_user'       => $user,
                'profile_avatar_url' => $profile_avatar_url,
                'posts'              => $posts,
                'is_following'       => $is_following,
                'follower_count'     => $follower_count,
                'following_count'    => $following_count,
                'can_message'        => $can_message,
            ]);
        }

        return $result;
    }

    /**
     * Displays the settings form for the current user.
     *
     * GET /settings
     *
     * @return string The rendered HTML.
     */
    public function showSettings(): string {
        $this->requireAuth();

        $user = $this->session->getCurrentUser();

        $avatar_url = $user->avatar_path !== null
            ? $this->image_service->getUrl($user->avatar_path)
            : null;

        return $this->renderThemed('pages/settings.html.twig', [
            'profile_user'   => $user,
            'avatar_url'     => $avatar_url,
            'max_bio_length' => $this->profile_service->getMaxBioLength(),
        ]);
    }

    /**
     * Handles settings form submission.
     *
     * POST /settings
     *
     * @return string The rendered HTML or redirect.
     */
    public function updateSettings(): string {
        $result = '';

        $this->requireAuth();

        if (!$this->validateCsrf()) {
            $this->session->addFlash('error', 'Invalid form submission. Please try again.');
            $this->redirect('/settings');
            return '';
        }

        $user = $this->session->getCurrentUser();
        $username = trim((string) $this->getPost('username', ''));
        $email = trim((string) $this->getPost('email', ''));
        $bio = (string) $this->getPost('bio', '');
        $name = (string) $this->getPost('name', '');

        $avatar_path = null;

        // Generate current avatar URL for error cases
        $avatar_url = $user->avatar_path !== null
            ? $this->image_service->getUrl($user->avatar_path)
            : null;

        // Handle avatar upload
        $file = $_FILES['avatar'] ?? null;

        if ($this->image_service->hasUpload($file)) {
            $upload_result = $this->image_service->upload($file, 'avatars');

            if (!$upload_result['success']) {
                $result = $this->renderThemed('pages/settings.html.twig', [
                    'profile_user'   => $user,
                    'avatar_url'     => $avatar_url,
                    'max_bio_length' => $this->profile_service->getMaxBioLength(),
                    'error'          => $upload_result['error'],
                    'username'       => $username,
                    'email'          => $email,
                    'bio'            => $bio,
                    'name'           => $name,
                ]);

                return $result;
            }

            // Delete old avatar if exists
            if ($user->avatar_path !== null) {
                $this->image_service->delete($user->avatar_path);
            }

            $avatar_path = $upload_result['path'];
        }

        $update_result = $this->profile_service->updateProfile(
            $user,
            $username,
            $email,
            $bio,
            $avatar_path,
            $name
        );

        if ($update_result['success']) {
            $this->session->addFlash('success', 'Profile updated.');
            $this->redirect('/settings');
        } else {
            $result = $this->renderThemed('pages/settings.html.twig', [
                'profile_user'   => $user,
                'avatar_url'     => $avatar_url,
                'max_bio_length' => $this->profile_service->getMaxBioLength(),
                'error'          => $update_result['error'],
                'username'       => $username,
                'email'          => $email,
                'bio'            => $bio,
                'name'           => $name,
            ]);
        }

        return $result;
    }

    /**
     * Handles password change.
     *
     * POST /settings/password
     *
     * @return void
     */
    public function updatePassword(): void {
        $this->requireAuth();

        if (!$this->validateCsrf()) {
            $this->session->addFlash('error', 'Invalid form submission. Please try again.');
            $this->redirect('/settings');
            return;
        }

        $user = $this->session->getCurrentUser();
        $current_password = (string) $this->getPost('current_password', '');
        $new_password = (string) $this->getPost('new_password', '');
        $confirm_password = (string) $this->getPost('confirm_password', '');

        if ($new_password !== $confirm_password) {
            $this->session->addFlash('error', 'New passwords do not match.');
        } else {
            $result = $this->profile_service->updatePassword($user, $current_password, $new_password);

            if ($result['success']) {
                $this->session->addFlash('success', 'Password updated.');
            } else {
                $this->session->addFlash('error', $result['error']);
            }
        }

        $this->redirect('/settings');
    }

    /**
     * Removes the user's avatar.
     *
     * POST /settings/avatar/remove
     *
     * @return void
     */
    public function removeAvatar(): void {
        $this->requireAuth();

        if (!$this->validateCsrf()) {
            $this->session->addFlash('error', 'Invalid form submission. Please try again.');
            $this->redirect('/settings');
            return;
        }

        $user = $this->session->getCurrentUser();
        $old_path = $this->profile_service->removeAvatar($user);

        if ($old_path !== null) {
            $this->image_service->delete($old_path);
        }

        $this->session->addFlash('success', 'Avatar removed.');
        $this->redirect('/settings');
    }

    /**
     * Follows a user.
     *
     * POST /user/{username}/follow
     *
     * @param string $username The username of the user to follow.
     *
     * @return void
     */
    public function follow(string $username): void {
        $this->requireAuth();

        if (!$this->validateCsrf()) {
            $this->session->addFlash('error', 'Invalid form submission. Please try again.');
            $this->redirect('/user/' . $username);
            return;
        }

        $current_user = $this->session->getCurrentUser();
        $user_to_follow = $this->profile_service->getByUsername($username);

        if ($user_to_follow === null) {
            $this->session->addFlash('error', 'User not found.');
        } else {
            $result = $this->user_follow_service->follow(
                $current_user->user_id,
                $user_to_follow->user_id
            );

            if ($result['success']) {
                $this->session->addFlash('success', 'You are now following ' . $username . '.');
            } else {
                $this->session->addFlash('error', $result['error']);
            }
        }

        $this->redirect('/user/' . $username);
    }

    /**
     * Unfollows a user.
     *
     * POST /user/{username}/unfollow
     *
     * @param string $username The username of the user to unfollow.
     *
     * @return void
     */
    public function unfollow(string $username): void {
        $this->requireAuth();

        if (!$this->validateCsrf()) {
            $this->session->addFlash('error', 'Invalid form submission. Please try again.');
            $this->redirect('/user/' . $username);
            return;
        }

        $current_user = $this->session->getCurrentUser();
        $user_to_unfollow = $this->profile_service->getByUsername($username);

        if ($user_to_unfollow === null) {
            $this->session->addFlash('error', 'User not found.');
        } else {
            $result = $this->user_follow_service->unfollow(
                $current_user->user_id,
                $user_to_unfollow->user_id
            );

            if ($result['success']) {
                $this->session->addFlash('success', 'You have unfollowed ' . $username . '.');
            } else {
                $this->session->addFlash('error', $result['error']);
            }
        }

        $this->redirect('/user/' . $username);
    }
}
