<?php

namespace App\Controllers\User;

use App\Core\Auth;
use App\Core\Controller;
use App\Models\Company;

class SettingsController extends Controller
{
    public function index(): void
    {
        $company = Company::find(Auth::companyId());
        $this->view('user/settings/index', ['pageTitle' => 'Settings', 'company' => $company], 'layouts/app');
    }

    public function update(): void
    {
        $this->verifyCsrf();
        if (!Auth::isCompanyOwner()) {
            $this->flash('error', 'Only the company owner can update company settings.');
            self::redirect('/app/settings');
        }

        Company::update(Auth::companyId(), [
            'name' => trim((string) $this->input('name')),
            'phone' => $this->input('phone', ''),
            'city' => $this->input('city', ''),
            'cr_number' => $this->input('cr_number', ''),
            'vat_number' => $this->input('vat_number', ''),
        ]);

        $this->flash('success', 'Company settings updated.');
        self::redirect('/app/settings');
    }
}
