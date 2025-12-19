<?php

declare(strict_types=1);

namespace Murmur\Controller;

use Murmur\Repository\SettingMapper;
use Murmur\Service\AuthService;
use Murmur\Service\SessionService;
use Twig\Environment;

/**
 * Controller for authentication routes.
 *
 * Handles user registration, login, and logout.
 */
class AuthController extends BaseController {

    /**
     * Authentication service.
     */
    protected AuthService $auth;

    /**
     * Creates a new AuthController instance.
     *
     * @param Environment    $twig           Twig environment for rendering.
     * @param SessionService $session        Session service.
     * @param SettingMapper  $setting_mapper Setting mapper.
     * @param AuthService    $auth           Authentication service.
     */
    public function __construct(Environment $twig, SessionService $session, SettingMapper $setting_mapper, AuthService $auth) {
        parent::__construct($twig, $session, $setting_mapper);
        $this->auth = $auth;
    }

    /**
     * Displays the registration form.
     *
     * GET /register
     *
     * @return string The rendered HTML.
     */
    public function showRegisterForm(): string {
        $this->requireGuest();

        return $this->renderThemed('pages/register.html.twig');
    }

    /**
     * Handles registration form submission.
     *
     * POST /register
     *
     * @return string The rendered HTML or redirect.
     */
    public function register(): string {
        $result = '';

        $this->requireGuest();

        if (!$this->validateCsrf()) {
            $this->session->addFlash('error', 'Invalid form submission. Please try again.');
            $result = $this->renderThemed('pages/register.html.twig');
        } else {
            $name = trim((string) $this->getPost('name', ''));
            $username = trim((string) $this->getPost('username', ''));
            $email = trim((string) $this->getPost('email', ''));
            $password = (string) $this->getPost('password', '');
            $password_confirm = (string) $this->getPost('password_confirm', '');

            if ($password !== $password_confirm) {
                $result = $this->renderThemed('pages/register.html.twig', [
                    'error' => 'Passwords do not match.',
                    'name' => $name,
                    'username' => $username,
                    'email' => $email,
                ]);
            } else {
                $auth_result = $this->auth->register($username, $email, $password, false, $name);

                if ($auth_result['success']) {
                    if (!empty($auth_result['pending'])) {
                        $this->session->addFlash('success', 'Your account has been created and is awaiting admin approval.');
                        $this->redirect('/login');
                    } else {
                        $this->session->login($auth_result['user']);
                        $this->session->addFlash('success', 'Welcome to Murmur!');
                        $this->redirect('/');
                    }
                } else {
                    $result = $this->renderThemed('pages/register.html.twig', [
                        'error' => $auth_result['error'],
                        'name' => $name,
                        'username' => $username,
                        'email' => $email,
                    ]);
                }
            }
        }

        return $result;
    }

    /**
     * Displays the login form.
     *
     * GET /login
     *
     * @return string The rendered HTML.
     */
    public function showLoginForm(): string {
        $this->requireGuest();

        return $this->renderThemed('pages/login.html.twig');
    }

    /**
     * Handles login form submission.
     *
     * POST /login
     *
     * @return string The rendered HTML or redirect.
     */
    public function login(): string {
        $result = '';

        $this->requireGuest();

        if (!$this->validateCsrf()) {
            $this->session->addFlash('error', 'Invalid form submission. Please try again.');
            $result = $this->renderThemed('pages/login.html.twig');
        } else {
            $email = trim((string) $this->getPost('email', ''));
            $password = (string) $this->getPost('password', '');

            $auth_result = $this->auth->login($email, $password);

            if ($auth_result['success']) {
                $this->session->login($auth_result['user']);

                // Rehash password if needed (algorithm upgrade)
                $this->auth->rehashPasswordIfNeeded($auth_result['user'], $password);

                $this->session->addFlash('success', 'Welcome back!');
                $this->redirect('/');
            } else {
                $result = $this->renderThemed('pages/login.html.twig', [
                    'error' => $auth_result['error'],
                    'email' => $email,
                ]);
            }
        }

        return $result;
    }

    /**
     * Handles logout.
     *
     * POST /logout
     *
     * @return void
     */
    public function logout(): void {
        if ($this->validateCsrf()) {
            $this->session->logout();
            $this->session->addFlash('success', 'You have been logged out.');
        }

        $this->redirect('/');
    }
}
