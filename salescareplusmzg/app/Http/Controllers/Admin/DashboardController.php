<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use App\Models\Faq;
use App\Models\NewsletterSubscriber;
use App\Models\Page;
use App\Models\Principal;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\TeamMember;
use App\Models\Testimonial;

class DashboardController extends Controller
{
    public function index()
    {
        $stats = [
            'products' => Product::count(),
            'categories' => ProductCategory::count(),
            'principals' => Principal::count(),
            'testimonials' => Testimonial::count(),
            'team_members' => TeamMember::count(),
            'faqs' => Faq::count(),
            'pages' => Page::count(),
            'newsletter_subscribers' => NewsletterSubscriber::count(),
            'unread_messages' => ContactMessage::where('is_read', false)->count(),
        ];

        $recentMessages = ContactMessage::latest()->limit(5)->get();

        return view('admin.dashboard.index', compact('stats', 'recentMessages'));
    }
}
