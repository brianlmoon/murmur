<?php

declare(strict_types=1);

/**
 * Murmur - A quiet, open social platform
 *
 * Main entry point for HTTP requests.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Murmur\Controller\AdminController;
use Murmur\Controller\AuthController;
use Murmur\Controller\MessageController;
use Murmur\Controller\PostController;
use Murmur\Controller\ProfileController;
use Murmur\Controller\SetupController;
use Murmur\Handler\DatabaseSessionHandler;
use Murmur\Repository\ConversationMapper;
use Murmur\Repository\LikeMapper;
use Murmur\Repository\LinkPreviewMapper;
use Murmur\Repository\MessageMapper;
use Murmur\Repository\PostAttachmentMapper;
use Murmur\Repository\PostMapper;
use Murmur\Repository\SessionMapper;
use Murmur\Repository\SettingMapper;
use Murmur\Repository\TopicFollowMapper;
use Murmur\Repository\TopicMapper;
use Murmur\Repository\UserBlockMapper;
use Murmur\Repository\UserFollowMapper;
use Murmur\Repository\UserMapper;
use Murmur\Service\AdminService;
use Murmur\Service\AuthService;
use Murmur\Service\MediaService;
use Murmur\Service\LikeService;
use Murmur\Service\LinkPreviewService;
use Murmur\Service\MessageService;
use Murmur\Service\PostService;
use Murmur\Service\ProfileService;
use Murmur\Service\SessionService;
use Murmur\Service\TopicService;
use Murmur\Service\TranslationService;
use Murmur\Service\UserBlockService;
use Murmur\Service\UserFollowService;
use Murmur\Storage\StorageFactory;
use Murmur\Twig\LinkifyExtension;
use Murmur\Twig\LocalizedDateExtension;
use Murmur\Twig\RelativeDateExtension;
use PageMill\Router\Router;
use Symfony\Bridge\Twig\Extension\TranslationExtension;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

// ---------------------------------------------------------------------------
// Bootstrap
// ---------------------------------------------------------------------------

// Initialize Twig
$twig_loader = new FilesystemLoader(__DIR__ . '/../templates');
$twig = new Environment($twig_loader, [
    'cache' => false, // Set to a path in production
    'auto_reload' => true,
    'autoescape' => 'html',
]);
$twig->addExtension(new RelativeDateExtension());
$twig->addExtension(new LinkifyExtension());

// Initialize Mappers
$user_mapper = new UserMapper();
$post_mapper = new PostMapper();
$post_attachment_mapper = new PostAttachmentMapper();
$setting_mapper = new SettingMapper();
$like_mapper = new LikeMapper();
$topic_mapper = new TopicMapper();
$topic_follow_mapper = new TopicFollowMapper();
$link_preview_mapper = new LinkPreviewMapper();
$user_follow_mapper = new UserFollowMapper();
$conversation_mapper = new ConversationMapper();
$message_mapper = new MessageMapper();
$user_block_mapper = new UserBlockMapper();

// Add global template variables
$base_url = $setting_mapper->getBaseUrl();
$twig->addGlobal('base_url', $base_url);
$twig->addGlobal('site_name', $setting_mapper->getSiteName());
$twig->addGlobal('images_allowed', $setting_mapper->areImagesAllowed());
$twig->addGlobal('videos_allowed', $setting_mapper->areVideosAllowed());
$twig->addGlobal('theme', $setting_mapper->getTheme());
$twig->addGlobal('logo_url', $setting_mapper->getLogoUrl());
$twig->addGlobal('has_topics', count($topic_mapper->findAll()) > 0);
$twig->addGlobal('max_attachments', $setting_mapper->getMaxAttachments());
$twig->addGlobal('max_video_size_mb', $setting_mapper->getMaxVideoSizeMb());

// Initialize Translation Service
$locale = $setting_mapper->getLocale();
$translation_service = new TranslationService($locale, __DIR__ . '/../translations');
$twig->addExtension(new TranslationExtension($translation_service->getTranslator()));
$twig->addExtension(new LocalizedDateExtension($translation_service->getTranslator()));
$twig->addGlobal('locale', $locale);

// Initialize Storage
// Load storage configuration from config.ini (falls back to local if not configured)
$config = \DealNews\GetConfig\GetConfig::init();

// Session configuration
$session_mapper   = new SessionMapper();
$session_lifetime = (int) ($config->get('session.lifetime') ?? 604800); // 7 days default

// Register database session handler
$session_handler = new DatabaseSessionHandler($session_mapper, $session_lifetime);
session_set_save_handler($session_handler, true);

// Configure session settings
ini_set('session.gc_maxlifetime', (string) $session_lifetime);
ini_set('session.cookie_lifetime', (string) $session_lifetime);
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_secure', (($_SERVER['HTTPS'] ?? '') === 'on' || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')) ? '1' : '0');
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.use_strict_mode', '1');

$storage_adapter = $config->get('storage.uploads.adapter') ?? 'local';

$storage_config = [
    'adapter'     => $storage_adapter,
    'local_path'  => $config->get('storage.uploads.local_path') ?? __DIR__ . '/uploads',
    'base_url'    => $config->get('storage.uploads.base_url') ?? ($base_url . '/uploads'),
    's3_key'      => $config->get('storage.uploads.s3_key') ?? '',
    's3_secret'   => $config->get('storage.uploads.s3_secret') ?? '',
    's3_region'   => $config->get('storage.uploads.s3_region') ?? '',
    's3_bucket'   => $config->get('storage.uploads.s3_bucket') ?? '',
    's3_endpoint' => $config->get('storage.uploads.s3_endpoint') ?? '',
];
$storage = StorageFactory::create($storage_config);

// Initialize Services
$session_service = new SessionService($user_mapper, $session_mapper);
$auth_service = new AuthService($user_mapper, $setting_mapper);
$post_service = new PostService($post_mapper, $user_mapper, $like_mapper, $topic_mapper, $setting_mapper, $post_attachment_mapper);
$profile_service = new ProfileService($user_mapper);
$admin_service = new AdminService($user_mapper, $post_mapper, $setting_mapper);
$max_video_size = $setting_mapper->getMaxVideoSizeMb() * 1024 * 1024;
$media_service = new MediaService($storage, $max_video_size);
$like_service = new LikeService($like_mapper);
$topic_service = new TopicService($topic_mapper, $topic_follow_mapper);
$link_preview_service = new LinkPreviewService($link_preview_mapper);
$user_follow_service = new UserFollowService($user_follow_mapper, $user_mapper);
$user_block_service = new UserBlockService($user_block_mapper, $user_mapper);
$message_service = new MessageService(
    $conversation_mapper,
    $message_mapper,
    $user_mapper,
    $setting_mapper,
    $user_follow_service,
    $user_block_service
);

// Initialize Controllers
$auth_controller = new AuthController($twig, $session_service, $setting_mapper, $auth_service);
$post_controller = new PostController($twig, $session_service, $setting_mapper, $post_service, $media_service, $like_service, $topic_service, $link_preview_service);
$profile_controller = new ProfileController($twig, $session_service, $setting_mapper, $profile_service, $post_service, $media_service, $user_follow_service, $message_service, $translation_service, $link_preview_service);
$admin_controller = new AdminController($twig, $session_service, $setting_mapper, $admin_service, $topic_service, $translation_service);
$setup_controller = new SetupController($twig, $session_service, $setting_mapper, $auth_service);
$message_controller = new MessageController($twig, $session_service, $setting_mapper, $message_service, $user_block_service, $user_mapper, $media_service);

// ---------------------------------------------------------------------------
// First-Run Setup Check
// ---------------------------------------------------------------------------

$full_request_uri = strtok($_SERVER['REQUEST_URI'], '?');
$needs_setup = $user_mapper->countAll() === 0;

// Strip base URL from request URI for routing
$request_uri = $full_request_uri;
if ($base_url !== '' && strpos($request_uri, $base_url) === 0) {
    $request_uri = substr($request_uri, strlen($base_url));
    if ($request_uri === '' || $request_uri === false) {
        $request_uri = '/';
    }
}

// For setup, we need to detect base URL before it's saved
if ($needs_setup) {
    $script_name = $_SERVER['SCRIPT_NAME'] ?? '';
    $detected_base = dirname($script_name);
    if ($detected_base === '/' || $detected_base === '\\' || $detected_base === '.') {
        $detected_base = '';
    } else {
        $detected_base = rtrim($detected_base, '/');
    }

    // Strip detected base from request for setup routing
    if ($detected_base !== '' && strpos($full_request_uri, $detected_base) === 0) {
        $request_uri = substr($full_request_uri, strlen($detected_base));
        if ($request_uri === '' || $request_uri === false) {
            $request_uri = '/';
        }
    }

    // Update Twig global so setup template can use the detected base URL
    $twig->addGlobal('base_url', $detected_base);

    if ($request_uri !== '/setup') {
        header('Location: ' . $detected_base . '/setup');
        exit;
    }
}

if (!$needs_setup && $request_uri === '/setup') {
    header('Location: ' . $base_url . '/');
    exit;
}

// ---------------------------------------------------------------------------
// Routes
// ---------------------------------------------------------------------------

$router = new Router();

// Setup routes (first-run only)
$router->add('exact', '/setup', ['controller' => $setup_controller, 'action' => 'showSetupForm'], ['method' => 'GET']);
$router->add('exact', '/setup', ['controller' => $setup_controller, 'action' => 'setup'], ['method' => 'POST']);

// Auth routes
$router->add('exact', '/register', ['controller' => $auth_controller, 'action' => 'showRegisterForm'], ['method' => 'GET']);
$router->add('exact', '/register', ['controller' => $auth_controller, 'action' => 'register'], ['method' => 'POST']);
$router->add('exact', '/login', ['controller' => $auth_controller, 'action' => 'showLoginForm'], ['method' => 'GET']);
$router->add('exact', '/login', ['controller' => $auth_controller, 'action' => 'login'], ['method' => 'POST']);
$router->add('exact', '/logout', ['controller' => $auth_controller, 'action' => 'logout'], ['method' => 'POST']);

// Post routes
$router->add('exact', '/', ['controller' => $post_controller, 'action' => 'feed'], ['method' => 'GET']);
$router->add('exact', '/post', ['controller' => $post_controller, 'action' => 'create'], ['method' => 'POST']);
$router->add('regex', '/^\/post\/(\d+)$/', ['controller' => $post_controller, 'action' => 'show'], ['method' => 'GET', 'tokens' => ['post_id']]);
$router->add('regex', '/^\/post\/(\d+)\/reply$/', ['controller' => $post_controller, 'action' => 'reply'], ['method' => 'POST', 'tokens' => ['post_id']]);
$router->add('regex', '/^\/post\/(\d+)\/delete$/', ['controller' => $post_controller, 'action' => 'delete'], ['method' => 'POST', 'tokens' => ['post_id']]);
$router->add('regex', '/^\/post\/(\d+)\/like$/', ['controller' => $post_controller, 'action' => 'like'], ['method' => 'POST', 'tokens' => ['post_id']]);

// Topic routes
$router->add('exact', '/topics', ['controller' => $post_controller, 'action' => 'topics'], ['method' => 'GET']);
$router->add('regex', '/^\/topic\/(\d+)$/', ['controller' => $post_controller, 'action' => 'topic'], ['method' => 'GET', 'tokens' => ['topic_id']]);
$router->add('regex', '/^\/topic\/(\d+)\/follow$/', ['controller' => $post_controller, 'action' => 'followTopic'], ['method' => 'POST', 'tokens' => ['topic_id']]);
$router->add('regex', '/^\/topic\/(\d+)\/unfollow$/', ['controller' => $post_controller, 'action' => 'unfollowTopic'], ['method' => 'POST', 'tokens' => ['topic_id']]);

// Profile routes
$router->add('exact', '/settings', ['controller' => $profile_controller, 'action' => 'showSettings'], ['method' => 'GET']);
$router->add('exact', '/settings', ['controller' => $profile_controller, 'action' => 'updateSettings'], ['method' => 'POST']);
$router->add('exact', '/settings/password', ['controller' => $profile_controller, 'action' => 'updatePassword'], ['method' => 'POST']);
$router->add('exact', '/settings/avatar/remove', ['controller' => $profile_controller, 'action' => 'removeAvatar'], ['method' => 'POST']);
$router->add('exact', '/settings/logout-devices', ['controller' => $profile_controller, 'action' => 'logoutAllDevices'], ['method' => 'POST']);
$router->add('regex', '/^\/user\/([a-zA-Z0-9_]+)$/', ['controller' => $profile_controller, 'action' => 'show'], ['method' => 'GET', 'tokens' => ['username']]);
$router->add('regex', '/^\/user\/([a-zA-Z0-9_]+)\/follow$/', ['controller' => $profile_controller, 'action' => 'follow'], ['method' => 'POST', 'tokens' => ['username']]);
$router->add('regex', '/^\/user\/([a-zA-Z0-9_]+)\/unfollow$/', ['controller' => $profile_controller, 'action' => 'unfollow'], ['method' => 'POST', 'tokens' => ['username']]);

// Message routes
$router->add('exact', '/messages', ['controller' => $message_controller, 'action' => 'inbox'], ['method' => 'GET']);
$router->add('exact', '/messages/search', ['controller' => $message_controller, 'action' => 'searchUsers'], ['method' => 'GET']);
$router->add('regex', '/^\/messages\/new\/([a-zA-Z0-9_]+)$/', ['controller' => $message_controller, 'action' => 'newConversation'], ['method' => 'GET', 'tokens' => ['username']]);
$router->add('regex', '/^\/messages\/(\d+)\/poll$/', ['controller' => $message_controller, 'action' => 'pollConversation'], ['method' => 'GET', 'tokens' => ['conversation_id']]);
$router->add('regex', '/^\/messages\/(\d+)$/', ['controller' => $message_controller, 'action' => 'showConversation'], ['method' => 'GET', 'tokens' => ['conversation_id']]);
$router->add('regex', '/^\/messages\/(\d+)\/send$/', ['controller' => $message_controller, 'action' => 'sendMessage'], ['method' => 'POST', 'tokens' => ['conversation_id']]);
$router->add('regex', '/^\/messages\/(\d+)\/delete$/', ['controller' => $message_controller, 'action' => 'deleteConversation'], ['method' => 'POST', 'tokens' => ['conversation_id']]);
$router->add('regex', '/^\/messages\/(\d+)\/delete\/(\d+)$/', ['controller' => $message_controller, 'action' => 'deleteMessage'], ['method' => 'POST', 'tokens' => ['conversation_id', 'message_id']]);
$router->add('regex', '/^\/messages\/block\/([a-zA-Z0-9_]+)$/', ['controller' => $message_controller, 'action' => 'blockUser'], ['method' => 'POST', 'tokens' => ['username']]);
$router->add('regex', '/^\/messages\/unblock\/([a-zA-Z0-9_]+)$/', ['controller' => $message_controller, 'action' => 'unblockUser'], ['method' => 'POST', 'tokens' => ['username']]);

// Admin routes
$router->add('exact', '/admin', ['controller' => $admin_controller, 'action' => 'dashboard'], ['method' => 'GET']);
$router->add('exact', '/admin/users', ['controller' => $admin_controller, 'action' => 'users'], ['method' => 'GET']);
$router->add('regex', '/^\/admin\/users\/(\d+)\/disable$/', ['controller' => $admin_controller, 'action' => 'disableUser'], ['method' => 'POST', 'tokens' => ['user_id']]);
$router->add('regex', '/^\/admin\/users\/(\d+)\/enable$/', ['controller' => $admin_controller, 'action' => 'enableUser'], ['method' => 'POST', 'tokens' => ['user_id']]);
$router->add('regex', '/^\/admin\/users\/(\d+)\/admin$/', ['controller' => $admin_controller, 'action' => 'toggleAdmin'], ['method' => 'POST', 'tokens' => ['user_id']]);
$router->add('exact', '/admin/settings', ['controller' => $admin_controller, 'action' => 'showSettings'], ['method' => 'GET']);
$router->add('exact', '/admin/settings', ['controller' => $admin_controller, 'action' => 'updateSettings'], ['method' => 'POST']);
$router->add('exact', '/admin/pending', ['controller' => $admin_controller, 'action' => 'pendingUsers'], ['method' => 'GET']);
$router->add('regex', '/^\/admin\/user\/(\d+)\/approve$/', ['controller' => $admin_controller, 'action' => 'approveUser'], ['method' => 'POST', 'tokens' => ['user_id']]);
$router->add('regex', '/^\/admin\/user\/(\d+)\/reject$/', ['controller' => $admin_controller, 'action' => 'rejectUser'], ['method' => 'POST', 'tokens' => ['user_id']]);
$router->add('exact', '/admin/topics', ['controller' => $admin_controller, 'action' => 'topics'], ['method' => 'GET']);
$router->add('exact', '/admin/topics', ['controller' => $admin_controller, 'action' => 'createTopic'], ['method' => 'POST']);
$router->add('regex', '/^\/admin\/topics\/(\d+)\/delete$/', ['controller' => $admin_controller, 'action' => 'deleteTopic'], ['method' => 'POST', 'tokens' => ['topic_id']]);

// ---------------------------------------------------------------------------
// Dispatch
// ---------------------------------------------------------------------------

// Override REQUEST_URI for routing (strip base URL)
$_SERVER['REQUEST_URI'] = $request_uri;

$route = $router->match();

if (empty($route)) {
    // Serve static files when using PHP's built-in dev server
    if (php_sapi_name() === 'cli-server') {
        $allowed_extensions = [
            'css'   => 'text/css',
            'js'    => 'application/javascript',
            'png'   => 'image/png',
            'jpg'   => 'image/jpeg',
            'jpeg'  => 'image/jpeg',
            'gif'   => 'image/gif',
            'svg'   => 'image/svg+xml',
            'ico'   => 'image/x-icon',
            'woff'  => 'font/woff',
            'woff2' => 'font/woff2',
            'webp'  => 'image/webp',
        ];

        $request_path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $extension = strtolower(pathinfo($request_path, PATHINFO_EXTENSION));

        if (isset($allowed_extensions[$extension])) {
            $file_path = realpath(__DIR__ . $request_path);
            $public_dir = realpath(__DIR__);

            // Security: ensure file is within public directory
            if ($file_path !== false && strpos($file_path, $public_dir) === 0 && is_file($file_path)) {
                header('Content-Type: ' . $allowed_extensions[$extension]);
                readfile($file_path);
                exit;
            }
        }
    }

    // 404 Not Found
    http_response_code(404);
    $session_service->start();
    $theme = $setting_mapper->getTheme();
    echo $twig->render($theme . '/pages/404.html.twig', [
        'message' => 'Page not found.',
        'current_user' => $session_service->getCurrentUser(),
        'csrf_token' => $session_service->getCsrfToken(),
        'flashes' => $session_service->getFlashes(),
    ]);
} else {
    $controller = $route['action']['controller'];
    $action = $route['action']['action'];
    $tokens = $route['tokens'] ?? [];

    // Check registration status for register routes
    if ($action === 'showRegisterForm' || $action === 'register') {
        if (!$setting_mapper->isRegistrationOpen()) {
            $session_service->start();
            $session_service->addFlash('error', 'Registration is currently closed.');
            header('Location: /login');
            exit;
        }
    }

    // Call the controller action with any route tokens
    $result = null;

    if (!empty($tokens)) {
        // Pass tokens as arguments to the action
        $args = array_values($tokens);

        // Convert numeric tokens to integers
        $args = array_map(function ($arg) {
            return is_numeric($arg) ? (int) $arg : $arg;
        }, $args);

        $result = $controller->$action(...$args);
    } else {
        $result = $controller->$action();
    }

    // Output the result if it's a string (rendered HTML)
    if (is_string($result)) {
        echo $result;
    }
}
