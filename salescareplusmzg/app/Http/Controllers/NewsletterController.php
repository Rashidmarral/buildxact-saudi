<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\GuardsAgainstSpam;
use App\Models\NewsletterSubscriber;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class NewsletterController extends Controller
{
    use GuardsAgainstSpam;

    public function store(Request $request): RedirectResponse
    {
        if ($this->isSpamSubmission($request)) {
            return redirect()
                ->back()
                ->with('newsletter_status', 'Thanks for subscribing! You\'ll hear from us with updates and offers.');
        }

        $validated = $request->validate([
            'email' => ['required', 'email', 'max:150'],
        ]);

        NewsletterSubscriber::firstOrCreate($validated);

        return redirect()
            ->back()
            ->with('newsletter_status', 'Thanks for subscribing! You\'ll hear from us with updates and offers.');
    }
}
