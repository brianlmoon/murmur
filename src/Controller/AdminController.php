<?php

declare(strict_types=1);

namespace Murmur\Controller;

use Murmur\Repository\SettingMapper;
use Murmur\Service\AdminService;
use Murmur\Service\SessionService;
use Murmur\Service\TopicService;
use Murmur\Service\TranslationService;
use Twig\Environment;

/**
 * Controller for admin panel routes.
 *
 * Handles user management, topic management, and instance settings.
 */
class AdminController extends BaseController {

    /**
     * Admin service for business logic.
     */
    protected AdminService $admin_service;

    /**
     * Topic service for topic management.
     */
    protected TopicService $topic_service;

    /**
     * Translation service for available locales.
     */
    protected TranslationService $translation_service;

    /**
     * Creates a new AdminController instance.
     *
     * @param Environment        $twig                Twig environment for rendering.
     * @param SessionService     $session             Session service.
     * @param SettingMapper      $setting_mapper      Setting mapper.
     * @param AdminService       $admin_service       Admin service.
     * @param TopicService       $topic_service       Topic service.
     * @param TranslationService $translation_service Translation service.
     */
    public function __construct(
        Environment $twig,
        SessionService $session,
        SettingMapper $setting_mapper,
        AdminService $admin_service,
        TopicService $topic_service,
        TranslationService $translation_service
    ) {
        parent::__construct($twig, $session, $setting_mapper);
        $this->admin_service = $admin_service;
        $this->topic_service = $topic_service;
        $this->translation_service = $translation_service;
    }

    /**
     * Requires the current user to be an admin.
     *
     * @return void
     */
    protected function requireAdmin(): void {
        $this->requireAuth();

        $user = $this->session->getCurrentUser();

        if ($user === null || !$user->is_admin) {
            http_response_code(403);
            echo $this->renderThemed('pages/403.html.twig', [
                'message' => 'You do not have permission to access this area.',
            ]);
            exit;
        }
    }

    /**
     * Displays the admin dashboard.
     *
     * GET /admin
     *
     * @return string The rendered HTML.
     */
    public function dashboard(): string {
        $this->requireAdmin();

        $stats = $this->admin_service->getStats();

        return $this->render('admin/dashboard.html.twig', [
            'stats' => $stats,
        ]);
    }

    /**
     * Displays the user list.
     *
     * GET /admin/users
     *
     * @return string The rendered HTML.
     */
    public function users(): string {
        $this->requireAdmin();

        $search_query = $this->getQuery('q');
        $search_query = $search_query !== null ? trim($search_query) : null;

        if ($search_query !== null && $search_query !== '') {
            $users = $this->admin_service->searchUsers($search_query);
        } else {
            $users = $this->admin_service->getUsers();
            $search_query = null;
        }

        return $this->render('admin/users.html.twig', [
            'users' => $users,
            'search_query' => $search_query,
        ]);
    }

    /**
     * Disables a user account.
     *
     * POST /admin/users/{id}/disable
     *
     * @param int $user_id The user ID to disable.
     *
     * @return void
     */
    public function disableUser(int $user_id): void {
        $this->requireAdmin();

        if (!$this->validateCsrf()) {
            $this->session->addFlash('error', 'Invalid form submission.');
            $this->redirect('/admin/users');
            return;
        }

        $admin_user = $this->session->getCurrentUser();
        $result = $this->admin_service->disableUser($user_id, $admin_user);

        if ($result['success']) {
            $this->session->addFlash('success', 'User disabled.');
        } else {
            $this->session->addFlash('error', $result['error']);
        }

        $this->redirect('/admin/users');
    }

    /**
     * Enables a user account.
     *
     * POST /admin/users/{id}/enable
     *
     * @param int $user_id The user ID to enable.
     *
     * @return void
     */
    public function enableUser(int $user_id): void {
        $this->requireAdmin();

        if (!$this->validateCsrf()) {
            $this->session->addFlash('error', 'Invalid form submission.');
            $this->redirect('/admin/users');
            return;
        }

        $result = $this->admin_service->enableUser($user_id);

        if ($result['success']) {
            $this->session->addFlash('success', 'User enabled.');
        } else {
            $this->session->addFlash('error', $result['error']);
        }

        $this->redirect('/admin/users');
    }

    /**
     * Toggles admin status for a user.
     *
     * POST /admin/users/{id}/admin
     *
     * @param int $user_id The user ID to update.
     *
     * @return void
     */
    public function toggleAdmin(int $user_id): void {
        $this->requireAdmin();

        if (!$this->validateCsrf()) {
            $this->session->addFlash('error', 'Invalid form submission.');
            $this->redirect('/admin/users');
            return;
        }

        $admin_user = $this->session->getCurrentUser();
        $result = $this->admin_service->toggleAdmin($user_id, $admin_user);

        if ($result['success']) {
            $this->session->addFlash('success', 'Admin status updated.');
        } else {
            $this->session->addFlash('error', $result['error']);
        }

        $this->redirect('/admin/users');
    }

    /**
     * Displays the instance settings form.
     *
     * GET /admin/settings
     *
     * @return string The rendered HTML.
     */
    public function showSettings(): string {
        $this->requireAdmin();

        $templates_path = dirname(__DIR__, 2) . '/templates';
        $topics = $this->topic_service->getAllTopics();

        return $this->render('admin/settings.html.twig', [
            'site_name'         => $this->admin_service->getSiteName(),
            'registration_open' => $this->admin_service->isRegistrationOpen(),
            'images_allowed'    => $this->admin_service->areImagesAllowed(),
            'theme'             => $this->admin_service->getTheme(),
            'logo_url'          => $this->admin_service->getLogoUrl(),
            'require_approval'  => $this->admin_service->isApprovalRequired(),
            'public_feed'       => $this->admin_service->isPublicFeed(),
            'require_topic'     => $this->admin_service->isTopicRequired(),
            'messaging_enabled' => $this->admin_service->isMessagingEnabled(),
            'max_post_length'   => $this->admin_service->getMaxPostLength(),
            'max_attachments'   => $this->admin_service->getMaxAttachments(),
            'locale'            => $this->admin_service->getLocale(),
            'available_themes'  => $this->admin_service->getAvailableThemes($templates_path),
            'available_locales' => $this->translation_service->getAvailableLocalesWithNames(),
            'topics'            => $topics,
        ]);
    }

    /**
     * Handles instance settings form submission.
     *
     * POST /admin/settings
     *
     * @return string The rendered HTML or redirect.
     */
    public function updateSettings(): string {
        $result = '';

        $this->requireAdmin();

        if (!$this->validateCsrf()) {
            $this->session->addFlash('error', 'Invalid form submission.');
            $this->redirect('/admin/settings');
            return '';
        }

        $site_name = (string) $this->getPost('site_name', '');
        $registration_open = $this->getPost('registration_open') === '1';
        $images_allowed = $this->getPost('images_allowed') === '1';
        $theme = (string) $this->getPost('theme', 'default');
        $logo_url = (string) $this->getPost('logo_url', '');
        $require_approval = $this->getPost('require_approval') === '1';
        $public_feed = $this->getPost('public_feed') === '1';
        $require_topic = $this->getPost('require_topic') === '1';
        $messaging_enabled = $this->getPost('messaging_enabled') === '1';
        $max_post_length = (int) ($this->getPost('max_post_length') ?? 500);
        $max_attachments = (int) ($this->getPost('max_attachments') ?? 10);
        $locale = (string) $this->getPost('locale', 'en-US');

        $templates_path = dirname(__DIR__, 2) . '/templates';
        $topics = $this->topic_service->getAllTopics();

        $update_result = $this->admin_service->updateSettings($site_name, $registration_open, $images_allowed, $theme, $logo_url, $require_approval, $public_feed, $require_topic, $messaging_enabled, $max_post_length, $max_attachments, $locale);

        if ($update_result['success']) {
            $this->session->addFlash('success', 'Settings saved.');
            $this->redirect('/admin/settings');
        } else {
            $result = $this->render('admin/settings.html.twig', [
                'site_name'         => $site_name,
                'registration_open' => $registration_open,
                'images_allowed'    => $images_allowed,
                'theme'             => $theme,
                'logo_url'          => $logo_url,
                'require_approval'  => $require_approval,
                'public_feed'       => $public_feed,
                'require_topic'     => $require_topic,
                'messaging_enabled' => $messaging_enabled,
                'max_post_length'   => $max_post_length,
                'max_attachments'   => $max_attachments,
                'locale'            => $locale,
                'available_themes'  => $this->admin_service->getAvailableThemes($templates_path),
                'available_locales' => $this->translation_service->getAvailableLocalesWithNames(),
                'topics'            => $topics,
                'error'             => $update_result['error'],
            ]);
        }

        return $result;
    }

    /**
     * Displays the pending users list.
     *
     * GET /admin/pending
     *
     * @return string The rendered HTML.
     */
    public function pendingUsers(): string {
        $this->requireAdmin();

        $users = $this->admin_service->getPendingUsers();

        return $this->render('admin/pending_users.html.twig', [
            'users' => $users,
        ]);
    }

    /**
     * Approves a pending user account.
     *
     * POST /admin/user/{id}/approve
     *
     * @param int $user_id The user ID to approve.
     *
     * @return void
     */
    public function approveUser(int $user_id): void {
        $this->requireAdmin();

        if (!$this->validateCsrf()) {
            $this->session->addFlash('error', 'Invalid form submission.');
            $this->redirect('/admin/pending');
            return;
        }

        $result = $this->admin_service->approveUser($user_id);

        if ($result['success']) {
            $this->session->addFlash('success', 'User approved.');
        } else {
            $this->session->addFlash('error', $result['error']);
        }

        $this->redirect('/admin/pending');
    }

    /**
     * Rejects a pending user account.
     *
     * POST /admin/user/{id}/reject
     *
     * @param int $user_id The user ID to reject.
     *
     * @return void
     */
    public function rejectUser(int $user_id): void {
        $this->requireAdmin();

        if (!$this->validateCsrf()) {
            $this->session->addFlash('error', 'Invalid form submission.');
            $this->redirect('/admin/pending');
            return;
        }

        $result = $this->admin_service->rejectUser($user_id);

        if ($result['success']) {
            $this->session->addFlash('success', 'User rejected and deleted.');
        } else {
            $this->session->addFlash('error', $result['error']);
        }

        $this->redirect('/admin/pending');
    }

    /**
     * Displays the topic management page.
     *
     * GET /admin/topics
     *
     * @return string The rendered HTML.
     */
    public function topics(): string {
        $this->requireAdmin();

        $topics = $this->topic_service->getAllTopics();

        return $this->render('admin/topics.html.twig', [
            'topics' => $topics,
            'max_name_length' => $this->topic_service->getMaxNameLength(),
        ]);
    }

    /**
     * Creates a new topic.
     *
     * POST /admin/topics
     *
     * @return void
     */
    public function createTopic(): void {
        $this->requireAdmin();

        if (!$this->validateCsrf()) {
            $this->session->addFlash('error', 'Invalid form submission.');
            $this->redirect('/admin/topics');
            return;
        }

        $name = (string) $this->getPost('name', '');
        $result = $this->topic_service->createTopic($name);

        if ($result['success']) {
            $this->session->addFlash('success', 'Topic created.');
        } else {
            $this->session->addFlash('error', $result['error']);
        }

        $this->redirect('/admin/topics');
    }

    /**
     * Deletes a topic.
     *
     * POST /admin/topics/{id}/delete
     *
     * @param int $topic_id The topic ID to delete.
     *
     * @return void
     */
    public function deleteTopic(int $topic_id): void {
        $this->requireAdmin();

        if (!$this->validateCsrf()) {
            $this->session->addFlash('error', 'Invalid form submission.');
            $this->redirect('/admin/topics');
            return;
        }

        $result = $this->topic_service->deleteTopic($topic_id);

        if ($result['success']) {
            $this->session->addFlash('success', 'Topic deleted.');
        } else {
            $this->session->addFlash('error', $result['error']);
        }

        $this->redirect('/admin/topics');
    }
}
