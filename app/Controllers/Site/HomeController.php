<?php

namespace App\Controllers\Site;

use App\Core\Controller;
use App\Models\Plan;

class HomeController extends Controller
{
    public function index(): void
    {
        $this->view('site/home', ['pageTitle' => 'Construction Management Software for Saudi Contractors']);
    }

    public function features(): void
    {
        $this->view('site/features', ['pageTitle' => 'Features']);
    }

    public function pricing(): void
    {
        $plans = Plan::query('SELECT * FROM plans WHERE is_active = 1 ORDER BY sort_order ASC')->fetchAll();
        $this->view('site/pricing', ['pageTitle' => 'Pricing', 'plans' => $plans]);
    }

    public function about(): void
    {
        $this->view('site/about', ['pageTitle' => 'About Us']);
    }

    public function contact(): void
    {
        $this->view('site/contact', ['pageTitle' => 'Contact Us']);
    }

    public function contactSubmit(): void
    {
        $this->verifyCsrf();
        $this->flash('success', 'Thanks for reaching out! Our Saudi sales team will contact you within one business day.');
        self::redirect('/contact');
    }

    public function privacy(): void
    {
        $this->view('site/legal', ['pageTitle' => 'Privacy Policy', 'heading' => 'Privacy Policy']);
    }

    public function terms(): void
    {
        $this->view('site/legal', ['pageTitle' => 'Terms of Service', 'heading' => 'Terms of Service']);
    }
}
