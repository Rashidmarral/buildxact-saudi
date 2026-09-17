<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Mail\ContactFormMail;
use App\Models\Lead;
use App\Models\LegalDocument;
use App\Models\Plan;
use App\Models\PlatformDocument;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

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
        $plans = Plan::where('is_active', true)->where('is_public', true)->orderBy('sort_order')->get();

        return view('site.pricing', compact('plans'));
    }

    public function about()
    {
        return view('site.about');
    }

    public function compliance()
    {
        return view('site.compliance');
    }

    public function certificates()
    {
        $documents = PlatformDocument::orderBy('sort_order')->orderBy('id')->get();

        return view('site.certificates', compact('documents'));
    }

    public function contact()
    {
        return view('site.contact');
    }

    public function submitContact(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'message' => ['required', 'string', 'max:5000'],
        ]);

        $supportEmail = Setting::get('support_email', config('mail.from.address'));

        if ($supportEmail) {
            Mail::to($supportEmail)->send(new ContactFormMail($data['name'], $data['email'], $data['message']));
        }

        Lead::capture([
            'name' => $data['name'],
            'email' => $data['email'],
            'message' => $data['message'],
        ], 'contact_form', notify: false);

        return back()->with('status', __('Thanks for reaching out! Our team will get back to you shortly.'));
    }

    public function legal(string $slug)
    {
        $document = LegalDocument::where('slug', $slug)->firstOrFail();

        return view('site.legal', compact('document'));
    }
}
