<?php

namespace App\Controllers;

use App\Core\BaseController;
use App\Models\User;

/**
 * Authentication Controller
 *
 * Handles user login, logout, and session management.
 *
 * @package    ClaudeScraper
 * @subpackage Controllers
 * @author     J.J. Johnson <visionquest716@gmail.com>
 */
class AuthController extends BaseController
{
    /** @var User The user model instance */
    private User $userModel;

    /**
     * AuthController constructor.
     */
    public function __construct()
    {
        $this->userModel = new User();
    }

    /**
     * Display the login page.
     *
     * @return void
     */
    public function showLogin(): void
    {
        if (is_authenticated()) {
            $this->redirect('/');
            return;
        }

        $this->render('auth.login', [
            'pageTitle' => 'Login - Claude Scraper',
        ], 'layouts.auth');
    }

    /**
     * Process login form submission.
     *
     * @return void
     */
    public function login(): void
    {
        $this->validateCsrf();

        $email = $this->input('email');
        $password = $this->rawInput('password');

        if (empty($email) || empty($password)) {
            $this->flash('error', 'Please enter both email and password.');
            $this->redirect('/login');
            return;
        }

        $user = $this->userModel->authenticate($email, $password);

        if ($user) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user'] = [
                'id' => $user['id'],
                'username' => $user['username'],
                'email' => $user['email'],
                'role' => $user['role'],
            ];

            // Regenerate session ID for security
            session_regenerate_id(true);

            $this->flash('success', 'Welcome back, ' . $user['username'] . '!');
            $this->redirect('/');
        } else {
            $this->flash('error', 'Invalid email or password.');
            $this->redirect('/login');
        }
    }

    /**
     * Log the user out and destroy the session.
     *
     * @return void
     */
    public function logout(): void
    {
        $_SESSION = [];
        session_destroy();
        header('Location: /login');
        exit;
    }
}
