<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    public function index()
    {
        return view('site.home');
    }

    public function features()
    {
        return view('site.features');
    }

    public function pricing()
    {
        $plans = Plan::where('is_active', true)->orderBy('sort_order')->get();

        return view('site.pricing', compact('plans'));
    }

    public function about()
    {
        return view('site.about');
    }

    public function contact()
    {
        return view('site.contact');
    }

    public function submitContact(Request $request)
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'message' => ['required', 'string', 'max:5000'],
        ]);

        // No transactional email provider wired up yet — see README.
        return back()->with('status', __('Thanks for reaching out! Our team will get back to you shortly.'));
    }

    public function legal(string $page)
    {
        if (! in_array($page, ['terms', 'privacy'], true)) {
            abort(404);
        }

        return view('site.legal', compact('page'));
    }
}
