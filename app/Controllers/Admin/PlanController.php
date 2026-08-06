<?php

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Models\Plan;

class PlanController extends Controller
{
    public function index(): void
    {
        $plans = Plan::all('sort_order ASC');
        $this->view('admin/plans/index', ['pageTitle' => 'Subscription Plans', 'plans' => $plans], 'layouts/admin');
    }

    public function create(): void
    {
        $this->view('admin/plans/form', ['pageTitle' => 'New Plan', 'plan' => null], 'layouts/admin');
    }

    public function store(): void
    {
        $this->verifyCsrf();
        $this->save(null);
        $this->flash('success', 'Plan created.');
        self::redirect('/admin/plans');
    }

    public function edit(string $id): void
    {
        $plan = Plan::find((int) $id);
        if (!$plan) {
            http_response_code(404);
            die('Plan not found.');
        }
        $this->view('admin/plans/form', ['pageTitle' => 'Edit Plan', 'plan' => $plan], 'layouts/admin');
    }

    public function update(string $id): void
    {
        $this->verifyCsrf();
        $plan = Plan::find((int) $id);
        if (!$plan) {
            http_response_code(404);
            die('Plan not found.');
        }
        $this->save($plan['id']);
        $this->flash('success', 'Plan updated.');
        self::redirect('/admin/plans');
    }

    public function destroy(string $id): void
    {
        $this->verifyCsrf();
        Plan::delete((int) $id);
        $this->flash('success', 'Plan deleted.');
        self::redirect('/admin/plans');
    }

    private function save(?int $id): void
    {
        $featuresLines = array_filter(array_map('trim', explode("\n", (string) $this->input('features', ''))));

        $data = [
            'slug' => strtolower(trim((string) $this->input('slug'))),
            'name' => trim((string) $this->input('name')),
            'tagline' => $this->input('tagline', ''),
            'price_monthly' => (float) $this->input('price_monthly', 0),
            'price_yearly' => (float) $this->input('price_yearly', 0),
            'max_users' => (int) $this->input('max_users', 5),
            'max_projects' => (int) $this->input('max_projects', 10),
            'features' => json_encode(array_values($featuresLines)),
            'is_active' => $this->input('is_active') ? 1 : 0,
            'sort_order' => (int) $this->input('sort_order', 0),
        ];

        if ($id === null) {
            Plan::create($data);
        } else {
            Plan::update($id, $data);
        }
    }
}
