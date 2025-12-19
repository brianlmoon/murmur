<?php

declare(strict_types=1);

namespace Murmur\Controller;

use Murmur\Repository\SettingMapper;
use Murmur\Service\SessionService;
use Twig\Environment;

/**
 * Base controller providing common functionality.
 */
abstract class BaseController {

    /**
     * Twig environment for rendering templates.
     */
    protected Environment $twig;

    /**
     * Session service for authentication state.
     */
    protected SessionService $session;

    /**
     * Setting mapper for theme access.
     */
    protected SettingMapper $setting_mapper;

    /**
     * Creates a new controller instance.
     *
     * @param Environment    $twig           Twig environment for rendering.
     * @param SessionService $session        Session service.
     * @param SettingMapper  $setting_mapper Setting mapper for theme access.
     */
    public function __construct(Environment $twig, SessionService $session, SettingMapper $setting_mapper) {
        $this->twig = $twig;
        $this->session = $session;
        $this->setting_mapper = $setting_mapper;
    }

    /**
     * Renders a Twig template with the given context.
     *
     * @param string               $template The template path.
     * @param array<string, mixed> $context  Template variables.
     *
     * @return string The rendered HTML.
     */
    protected function render(string $template, array $context = []): string {
        // Add common variables to all templates
        $context['current_user'] = $this->session->getCurrentUser();
        $context['csrf_token'] = $this->session->getCsrfToken();
        $context['flashes'] = $this->session->getFlashes();

        return $this->twig->render($template, $context);
    }

    /**
     * Renders a themed template (uses current theme for user-facing pages).
     *
     * @param string               $template The template path (e.g., 'pages/feed.html.twig').
     * @param array<string, mixed> $context  Template variables.
     *
     * @return string The rendered HTML.
     */
    protected function renderThemed(string $template, array $context = []): string {
        $theme = $this->setting_mapper->getTheme();
        $themed_template = $theme . '/' . $template;

        return $this->render($themed_template, $context);
    }

    /**
     * Redirects to a URL.
     *
     * Automatically prepends the base URL for subdirectory installations.
     *
     * @param string $url The URL to redirect to (relative to base).
     *
     * @return void
     */
    protected function redirect(string $url): void {
        $base_url = $this->setting_mapper->getBaseUrl();
        header('Location: ' . $base_url . $url);
        exit;
    }

    /**
     * Gets a POST parameter.
     *
     * @param string $key     The parameter name.
     * @param mixed  $default Default value if not set.
     *
     * @return mixed The parameter value.
     */
    protected function getPost(string $key, mixed $default = null): mixed {
        $result = $default;

        if (isset($_POST[$key])) {
            $result = $_POST[$key];
        }

        return $result;
    }

    /**
     * Gets a GET parameter.
     *
     * @param string $key     The parameter name.
     * @param mixed  $default Default value if not set.
     *
     * @return mixed The parameter value.
     */
    protected function getQuery(string $key, mixed $default = null): mixed {
        $result = $default;

        if (isset($_GET[$key])) {
            $result = $_GET[$key];
        }

        return $result;
    }

    /**
     * Validates that the request includes a valid CSRF token.
     *
     * @return bool True if the CSRF token is valid.
     */
    protected function validateCsrf(): bool {
        $token = $this->getPost('csrf_token', '');

        return $this->session->validateCsrfToken($token);
    }

    /**
     * Requires the user to be logged in.
     *
     * @param string $redirect_url URL to redirect to if not logged in.
     *
     * @return void
     */
    protected function requireAuth(string $redirect_url = '/login'): void {
        if (!$this->session->isLoggedIn()) {
            $this->session->addFlash('error', 'Please log in to continue.');
            $this->redirect($redirect_url);
        }
    }

    /**
     * Requires the user to be a guest (not logged in).
     *
     * @param string $redirect_url URL to redirect to if logged in.
     *
     * @return void
     */
    protected function requireGuest(string $redirect_url = '/'): void {
        if ($this->session->isLoggedIn()) {
            $this->redirect($redirect_url);
        }
    }
}
