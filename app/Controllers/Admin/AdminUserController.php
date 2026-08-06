<?php

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Controller;
use App\Models\User;

class AdminUserController extends Controller
{
    public function index(): void
    {
        $admins = User::where('role', 'super_admin', 'created_at ASC');
        $this->view('admin/admins/index', ['pageTitle' => 'Admin Users', 'admins' => $admins], 'layouts/admin');
    }

    public function store(): void
    {
        $this->verifyCsrf();
        $name = trim((string) $this->input('name'));
        $email = strtolower(trim((string) $this->input('email')));
        $password = (string) $this->input('password');

        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 8) {
            $this->flash('error', 'A valid name, email, and password (min 8 chars) are required.');
            self::redirect('/admin/admins');
        }
        if (User::first('email', $email)) {
            $this->flash('error', 'A user with this email already exists.');
            self::redirect('/admin/admins');
        }

        User::create([
            'company_id' => null,
            'name' => $name,
            'email' => $email,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'role' => 'super_admin',
            'status' => 'active',
        ]);

        $this->flash('success', 'Admin user created.');
        self::redirect('/admin/admins');
    }

    public function destroy(string $id): void
    {
        $this->verifyCsrf();
        $admin = User::find((int) $id);
        if (!$admin || $admin['role'] !== 'super_admin') {
            http_response_code(404);
            die('Admin user not found.');
        }
        if ((int) $admin['id'] === (int) Auth::user()['id']) {
            $this->flash('error', 'You cannot remove your own admin account.');
            self::redirect('/admin/admins');
        }
        if (User::count("role = 'super_admin'") <= 1) {
            $this->flash('error', 'At least one admin account must remain.');
            self::redirect('/admin/admins');
        }
        User::delete($admin['id']);
        $this->flash('success', 'Admin user removed.');
        self::redirect('/admin/admins');
    }
}
