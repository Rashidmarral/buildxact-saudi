<?php

namespace App\Core;

use App\Models\User;

class Auth
{
    public static function attempt(string $email, string $password): bool
    {
        $user = User::first('email', strtolower(trim($email)));
        if (!$user || !password_verify($password, $user['password_hash'])) {
            return false;
        }
        if ($user['status'] !== 'active') {
            return false;
        }
        self::login($user);
        return true;
    }

    public static function login(array $user): void
    {
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
    }

    public static function logout(): void
    {
        unset($_SESSION['user_id']);
        session_regenerate_id(true);
    }

    public static function check(): bool
    {
        return isset($_SESSION['user_id']);
    }

    public static function user(): ?array
    {
        static $cached = null;
        if (!self::check()) {
            return null;
        }
        if ($cached === null) {
            $cached = User::find((int) $_SESSION['user_id']);
        }
        return $cached;
    }

    public static function isSuperAdmin(): bool
    {
        $u = self::user();
        return $u && $u['role'] === 'super_admin';
    }

    public static function isCompanyOwner(): bool
    {
        $u = self::user();
        return $u && $u['role'] === 'owner';
    }

    public static function companyId(): ?int
    {
        $u = self::user();
        return $u['company_id'] ?? null;
    }

    public static function requireLogin(): void
    {
        if (!self::check()) {
            Controller::redirect('/login');
        }
    }

    public static function requireSuperAdmin(): void
    {
        self::requireLogin();
        if (!self::isSuperAdmin()) {
            http_response_code(403);
            die('Forbidden: admin access only.');
        }
    }

    public static function requireCompanyUser(): void
    {
        self::requireLogin();
        if (self::isSuperAdmin()) {
            Controller::redirect('/admin');
        }
    }
}
