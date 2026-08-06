<?php

namespace App\Controllers\User;

use App\Core\Auth;
use App\Core\Controller;
use App\Models\User;

class TeamController extends Controller
{
    public function index(): void
    {
        $members = User::where('company_id', Auth::companyId(), 'created_at ASC');
        $this->view('user/team/index', ['pageTitle' => 'Team', 'members' => $members], 'layouts/app');
    }

    public function store(): void
    {
        $this->verifyCsrf();
        if (!Auth::isCompanyOwner()) {
            $this->flash('error', 'Only the company owner can invite team members.');
            self::redirect('/app/team');
        }

        $name = trim((string) $this->input('name'));
        $email = strtolower(trim((string) $this->input('email')));

        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->flash('error', 'A valid name and email are required.');
            self::redirect('/app/team');
        }
        if (User::first('email', $email)) {
            $this->flash('error', 'A user with this email already exists.');
            self::redirect('/app/team');
        }

        $tempPassword = bin2hex(random_bytes(4));
        User::create([
            'company_id' => Auth::companyId(),
            'name' => $name,
            'email' => $email,
            'password_hash' => password_hash($tempPassword, PASSWORD_DEFAULT),
            'role' => $this->input('role', 'staff'),
            'status' => 'active',
        ]);

        $this->flash('success', "Team member invited. Temporary password: {$tempPassword} (share this securely).");
        self::redirect('/app/team');
    }

    public function destroy(string $id): void
    {
        $this->verifyCsrf();
        $member = User::find((int) $id);
        if (!$member || (int) $member['company_id'] !== Auth::companyId()) {
            http_response_code(404);
            die('Team member not found.');
        }
        if ((int) $member['id'] === (int) Auth::user()['id']) {
            $this->flash('error', 'You cannot remove yourself.');
            self::redirect('/app/team');
        }
        User::delete($member['id']);
        $this->flash('success', 'Team member removed.');
        self::redirect('/app/team');
    }
}
