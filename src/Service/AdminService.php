<?php

declare(strict_types=1);

namespace Murmur\Service;

use Murmur\Entity\User;
use Murmur\Repository\PostMapper;
use Murmur\Repository\SettingMapper;
use Murmur\Repository\UserMapper;
use Murmur\Service\OAuthConfigService;

/**
 * Service for admin operations.
 *
 * Handles user management and instance settings.
 */
class AdminService {

    /**
     * The user mapper for database operations.
     */
    protected UserMapper $user_mapper;

    /**
     * The post mapper for database operations.
     */
    protected PostMapper $post_mapper;

    /**
     * The setting mapper for database operations.
     */
    protected SettingMapper $setting_mapper;

    /**
     * OAuth configuration service.
     */
    protected OAuthConfigService $oauth_config;

    /**
     * Creates a new AdminService instance.
     *
     * @param UserMapper         $user_mapper    The user mapper.
     * @param PostMapper         $post_mapper    The post mapper.
     * @param SettingMapper      $setting_mapper The setting mapper.
     * @param OAuthConfigService $oauth_config   OAuth configuration service.
     */
    public function __construct(
        UserMapper $user_mapper,
        PostMapper $post_mapper,
        SettingMapper $setting_mapper,
        OAuthConfigService $oauth_config
    ) {
        $this->user_mapper = $user_mapper;
        $this->post_mapper = $post_mapper;
        $this->setting_mapper = $setting_mapper;
        $this->oauth_config = $oauth_config;
    }

    /**
     * Gets all users for admin management.
     *
     * @param int $limit  Maximum users to return.
     * @param int $offset Number of users to skip.
     *
     * @return array<User>
     */
    public function getUsers(int $limit = 50, int $offset = 0): array {
        return $this->user_mapper->find([], $limit, $offset, 'created_at DESC') ?? [];
    }

    /**
     * Gets a user by ID.
     *
     * @param int $user_id The user ID.
     *
     * @return User|null The user or null.
     */
    public function getUser(int $user_id): ?User {
        return $this->user_mapper->load($user_id);
    }

    /**
     * Searches for users by username or email.
     *
     * @param string $query  The search query.
     * @param int    $limit  Maximum users to return.
     * @param int    $offset Number of users to skip.
     *
     * @return array<User> Array of matching users.
     */
    public function searchUsers(string $query, int $limit = 50, int $offset = 0): array {
        $result = [];

        $query = trim($query);

        if (mb_strlen($query) >= 2) {
            $result = $this->user_mapper->searchUsers($query, $limit, $offset);
        }

        return $result;
    }

    /**
     * Disables a user account.
     *
     * @param int  $user_id    The user to disable.
     * @param User $admin_user The admin performing the action.
     *
     * @return array{success: bool, error?: string}
     */
    public function disableUser(int $user_id, User $admin_user): array {
        $result = ['success' => false];

        $user = $this->user_mapper->load($user_id);

        if ($user === null) {
            $result['error'] = 'User not found.';
        } elseif ($user->user_id === $admin_user->user_id) {
            $result['error'] = 'You cannot disable your own account.';
        } else {
            $user->is_disabled = true;
            $this->user_mapper->save($user);
            $result['success'] = true;
        }

        return $result;
    }

    /**
     * Enables a user account.
     *
     * @param int $user_id The user to enable.
     *
     * @return array{success: bool, error?: string}
     */
    public function enableUser(int $user_id): array {
        $result = ['success' => false];

        $user = $this->user_mapper->load($user_id);

        if ($user === null) {
            $result['error'] = 'User not found.';
        } else {
            $user->is_disabled = false;
            $this->user_mapper->save($user);
            $result['success'] = true;
        }

        return $result;
    }

    /**
     * Toggles admin status for a user.
     *
     * @param int  $user_id    The user to update.
     * @param User $admin_user The admin performing the action.
     *
     * @return array{success: bool, error?: string}
     */
    public function toggleAdmin(int $user_id, User $admin_user): array {
        $result = ['success' => false];

        $user = $this->user_mapper->load($user_id);

        if ($user === null) {
            $result['error'] = 'User not found.';
        } elseif ($user->user_id === $admin_user->user_id) {
            $result['error'] = 'You cannot change your own admin status.';
        } else {
            $user->is_admin = !$user->is_admin;
            $this->user_mapper->save($user);
            $result['success'] = true;
        }

        return $result;
    }

    /**
     * Gets instance statistics for the dashboard.
     *
     * @return array{user_count: int, post_count: int, recent_users: array<User>}
     */
    public function getStats(): array {
        $users = $this->user_mapper->find([]);
        $posts = $this->post_mapper->find([]);
        $recent_users = $this->user_mapper->find([], 5, 0, 'created_at DESC') ?? [];

        return [
            'user_count' => count($users ?? []),
            'post_count' => count($posts ?? []),
            'recent_users' => $recent_users,
        ];
    }

    /**
     * Gets the site name.
     *
     * @return string The site name.
     */
    public function getSiteName(): string {
        return $this->setting_mapper->getSiteName();
    }

    /**
     * Gets whether registration is open.
     *
     * @return bool True if registration is open.
     */
    public function isRegistrationOpen(): bool {
        return $this->setting_mapper->isRegistrationOpen();
    }

    /**
     * Checks if image uploads are allowed.
     *
     * @return bool True if images are allowed.
     */
    public function areImagesAllowed(): bool {
        return $this->setting_mapper->areImagesAllowed();
    }

    /**
     * Gets the current theme name.
     *
     * @return string The theme name.
     */
    public function getTheme(): string {
        return $this->setting_mapper->getTheme();
    }

    /**
     * Gets the logo URL.
     *
     * @return string The logo URL or empty string.
     */
    public function getLogoUrl(): string {
        return $this->setting_mapper->getLogoUrl();
    }

    /**
     * Gets available themes by scanning the templates directory.
     *
     * @param string $templates_path Path to the templates directory.
     *
     * @return array<string> List of available theme names.
     */
    public function getAvailableThemes(string $templates_path): array {
        $themes = [];

        if (is_dir($templates_path)) {
            $items = scandir($templates_path);

            if ($items !== false) {
                foreach ($items as $item) {
                    $item_path = $templates_path . '/' . $item;

                    // Skip hidden files, admin directory, and non-directories
                    if ($item[0] !== '.' && $item !== 'admin' && is_dir($item_path)) {
                        $themes[] = $item;
                    }
                }
            }
        }

        sort($themes);

        return $themes;
    }

    /**
     * Updates instance settings.
     *
     * @param string $site_name           The site name.
     * @param bool   $registration_open   Whether registration is open.
     * @param bool   $images_allowed      Whether image uploads are allowed.
     * @param string $theme               The theme name.
     * @param string $logo_url            Optional logo URL.
     * @param bool   $require_approval    Whether admin approval is required for new accounts.
     * @param bool   $public_feed         Whether non-logged-in users can view posts.
     * @param bool   $require_topic       Whether a topic must be selected when creating posts.
     * @param bool   $messaging_enabled   Whether private messaging is enabled.
     * @param int    $max_post_length     Maximum characters allowed per post.
     * @param int    $max_attachments     Maximum media attachments allowed per post.
     * @param string $locale              The locale code (e.g., 'en-US').
     * @param bool   $videos_allowed      Whether video uploads are allowed.
     * @param int    $max_video_size_mb   Maximum video file size in megabytes.
     * @param bool   $oauth_google_enabled Whether Google OAuth is enabled.
     * @param bool   $oauth_facebook_enabled Whether Facebook OAuth is enabled.
     * @param bool   $oauth_apple_enabled Whether Apple OAuth is enabled.
     *
     * @return array{success: bool, error?: string}
     */
    public function updateSettings(
        string $site_name,
        bool $registration_open,
        bool $images_allowed,
        string $theme,
        string $logo_url = '',
        bool $require_approval = false,
        bool $public_feed = true,
        bool $require_topic = false,
        bool $messaging_enabled = true,
        int $max_post_length = 500,
        int $max_attachments = 10,
        string $locale = 'en-US',
        bool $videos_allowed = true,
        int $max_video_size_mb = 100,
        bool $oauth_google_enabled = false,
        bool $oauth_facebook_enabled = false,
        bool $oauth_apple_enabled = false
    ): array {
        $result = ['success' => false];

        $site_name = trim($site_name);
        $theme = trim($theme);
        $logo_url = trim($logo_url);
        $locale = trim($locale);

        if ($site_name === '') {
            $result['error'] = 'Site name cannot be empty.';
        } elseif (strlen($site_name) > 50) {
            $result['error'] = 'Site name cannot exceed 50 characters.';
        } elseif ($theme === '') {
            $result['error'] = 'Theme cannot be empty.';
        } elseif ($max_post_length < 100) {
            $result['error'] = 'Maximum post length must be at least 100 characters.';
        } elseif ($max_post_length > 50000) {
            $result['error'] = 'Maximum post length cannot exceed 50,000 characters.';
        } elseif ($max_attachments < 1) {
            $result['error'] = 'Maximum attachments must be at least 1.';
        } elseif ($locale === '') {
            $result['error'] = 'Locale cannot be empty.';
        } elseif ($max_video_size_mb < 1) {
            $result['error'] = 'Maximum video size must be at least 1 MB.';
        } elseif ($max_video_size_mb > 1000) {
            $result['error'] = 'Maximum video size cannot exceed 1000 MB.';
        } else {
            $this->setting_mapper->saveSetting('site_name', $site_name);
            $this->setting_mapper->saveSetting('registration_open', $registration_open ? '1' : '0');
            $this->setting_mapper->saveSetting('images_allowed', $images_allowed ? '1' : '0');
            $this->setting_mapper->saveSetting('theme', $theme);
            $this->setting_mapper->saveSetting('logo_url', $logo_url);
            $this->setting_mapper->saveSetting('require_approval', $require_approval ? '1' : '0');
            $this->setting_mapper->saveSetting('public_feed', $public_feed ? '1' : '0');
            $this->setting_mapper->saveSetting('require_topic', $require_topic ? '1' : '0');
            $this->setting_mapper->saveSetting('messaging_enabled', $messaging_enabled ? '1' : '0');
            $this->setting_mapper->saveSetting('max_post_length', (string) $max_post_length);
            $this->setting_mapper->saveSetting('max_attachments', (string) $max_attachments);
            $this->setting_mapper->saveSetting('locale', $locale);
            $this->setting_mapper->saveSetting('videos_allowed', $videos_allowed ? '1' : '0');
            $this->setting_mapper->saveSetting('max_video_size_mb', (string) $max_video_size_mb);
            $this->setting_mapper->saveSetting('oauth_google_enabled', $oauth_google_enabled ? '1' : '0');
            $this->setting_mapper->saveSetting('oauth_facebook_enabled', $oauth_facebook_enabled ? '1' : '0');
            $this->setting_mapper->saveSetting('oauth_apple_enabled', $oauth_apple_enabled ? '1' : '0');
            $result['success'] = true;
        }

        return $result;
    }

    /**
     * Checks if the feed is publicly viewable.
     *
     * @return bool True if non-logged-in users can view posts.
     */
    public function isPublicFeed(): bool {
        return $this->setting_mapper->isPublicFeed();
    }

    /**
     * Checks if admin approval is required for new accounts.
     *
     * @return bool True if approval is required.
     */
    public function isApprovalRequired(): bool {
        return $this->setting_mapper->isApprovalRequired();
    }

    /**
     * Checks if a topic is required when creating posts.
     *
     * @return bool True if topic selection is required.
     */
    public function isTopicRequired(): bool {
        return $this->setting_mapper->isTopicRequired();
    }

    /**
     * Checks if private messaging is enabled.
     *
     * @return bool True if messaging is enabled.
     */
    public function isMessagingEnabled(): bool {
        return $this->setting_mapper->isMessagingEnabled();
    }

    /**
     * Gets the maximum post body length.
     *
     * @return int The maximum length in characters.
     */
    public function getMaxPostLength(): int {
        return $this->setting_mapper->getMaxPostLength();
    }

    /**
     * Gets the maximum number of attachments allowed per post.
     *
     * @return int The maximum attachment count.
     */
    public function getMaxAttachments(): int {
        return $this->setting_mapper->getMaxAttachments();
    }

    /**
     * Gets the current locale setting.
     *
     * @return string The locale code (e.g., 'en-US').
     */
    public function getLocale(): string {
        return $this->setting_mapper->getLocale();
    }

    /**
     * Checks if video uploads are allowed.
     *
     * @return bool True if videos are allowed.
     */
    public function areVideosAllowed(): bool {
        return $this->setting_mapper->areVideosAllowed();
    }

    /**
     * Gets the maximum video file size in megabytes.
     *
     * @return int The maximum video size in MB.
     */
    public function getMaxVideoSizeMb(): int {
        return $this->setting_mapper->getMaxVideoSizeMb();
    }

    /**
     * Gets all users pending admin approval.
     *
     * @return array<User> Array of pending users.
     */
    public function getPendingUsers(): array {
        return $this->user_mapper->findPendingUsers();
    }

    /**
     * Counts users pending admin approval.
     *
     * @return int The pending user count.
     */
    public function getPendingUserCount(): int {
        return $this->user_mapper->countPending();
    }

    /**
     * Approves a pending user account.
     *
     * @param int $user_id The user to approve.
     *
     * @return array{success: bool, error?: string}
     */
    public function approveUser(int $user_id): array {
        $result = ['success' => false];

        $user = $this->user_mapper->load($user_id);

        if ($user === null) {
            $result['error'] = 'User not found.';
        } elseif (!$user->is_pending) {
            $result['error'] = 'User is not pending approval.';
        } else {
            $user->is_pending = false;
            $this->user_mapper->save($user);
            $result['success'] = true;
        }

        return $result;
    }

    /**
     * Rejects a pending user account by deleting it.
     *
     * @param int $user_id The user to reject.
     *
     * @return array{success: bool, error?: string}
     */
    public function rejectUser(int $user_id): array {
        $result = ['success' => false];

        $user = $this->user_mapper->load($user_id);

        if ($user === null) {
            $result['error'] = 'User not found.';
        } elseif (!$user->is_pending) {
            $result['error'] = 'User is not pending approval.';
        } else {
            $this->user_mapper->delete($user_id);
            $result['success'] = true;
        }

        return $result;
    }

    /**
     * Gets OAuth provider configuration status.
     *
     * @return array<string, array{configured: bool, enabled: bool}>
     */
    public function getOAuthProviderStatus(): array {
        $result = [];

        foreach (OAuthConfigService::PROVIDERS as $provider) {
            $result[$provider] = [
                'configured' => $this->oauth_config->isConfigured($provider),
                'enabled'    => $this->setting_mapper->getSetting("oauth_{$provider}_enabled", '0') === '1',
            ];
        }

        return $result;
    }
}
