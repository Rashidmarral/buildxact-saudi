<?php

namespace App\Http\Controllers;

use App\Mail\ClientPortalLoginMail;
use App\Models\Client;
use App\Models\ClientPortalLogin;
use App\Models\InvoicePayment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * A self-service portal for a company's clients — full invoice history and
 * a running account statement, not just the single-invoice pay links
 * PublicInvoiceController already offers. Signing in is a magic link
 * (Client records have no password), and viewing/paying an individual
 * invoice deliberately reuses the existing public invoice page rather than
 * re-implementing it here.
 */
class ClientPortalController extends Controller
{
    public function showLogin()
    {
        return view('portal.login');
    }

    public function sendLoginLink(Request $request)
    {
        $data = $request->validate(['email' => ['required', 'email']]);

        // Never reveal whether the email matches a client — same reasoning
        // as the password-reset flow: the response is identical either way.
        $clients = Client::whereRaw('LOWER(email) = ?', [Str::lower($data['email'])])->get();

        foreach ($clients as $client) {
            $token = ClientPortalLogin::issueFor($client);
            $loginUrl = route('portal.authenticate', $token);

            Mail::to($client->email)->send(new ClientPortalLoginMail($client, $loginUrl));
        }

        return back()->with('status', __('If that email matches an account on file, a sign-in link is on its way.'));
    }

    public function authenticate(Request $request, string $token)
    {
        $client = ClientPortalLogin::consume($token);

        if (! $client) {
            return redirect()->route('portal.login')->withErrors(['email' => __('That sign-in link is invalid or has expired. Request a new one below.')]);
        }

        $request->session()->regenerate();
        $request->session()->put('portal_client_id', $client->id);

        return redirect()->route('portal.dashboard');
    }

    public function logout(Request $request)
    {
        $request->session()->forget('portal_client_id');
        $request->session()->regenerate();

        return redirect()->route('portal.login');
    }

    public function dashboard(Request $request)
    {
        $client = $request->attributes->get('portalClient');
        $invoices = $client->invoices()->whereNotIn('status', ['draft', 'cancelled'])->get();

        $outstanding = $invoices->sum(fn ($invoice) => max(0, $invoice->balanceDue()));
        $recentInvoices = $client->invoices()->whereNotIn('status', ['draft'])->latest('issue_date')->latest('id')->take(5)->get();

        return view('portal.dashboard', [
            'client' => $client,
            'outstanding' => $outstanding,
            'totalInvoiced' => $invoices->sum('total'),
            'openCount' => $invoices->where('status', '!=', 'paid')->count(),
            'recentInvoices' => $recentInvoices,
        ]);
    }

    public function invoices(Request $request)
    {
        $client = $request->attributes->get('portalClient');
        $invoices = $client->invoices()->whereNotIn('status', ['draft'])->latest('issue_date')->latest('id')->paginate(20);

        return view('portal.invoices', ['client' => $client, 'invoices' => $invoices]);
    }

    public function quotations(Request $request)
    {
        $client = $request->attributes->get('portalClient');
        $quotations = $client->quotations()
            ->whereNotIn('status', ['draft', 'pending_approval'])
            ->latest('issue_date')->latest('id')->paginate(20);

        return view('portal.quotations', ['client' => $client, 'quotations' => $quotations]);
    }

    public function statement(Request $request)
    {
        $client = $request->attributes->get('portalClient');
        $lines = $this->statementLines($client);

        return view('portal.statement', ['client' => $client, 'lines' => $lines]);
    }

    private function statementLines(Client $client)
    {
        $invoices = $client->invoices()->whereNotIn('status', ['draft', 'cancelled'])->get();
        $payments = InvoicePayment::whereIn('invoice_id', $invoices->pluck('id'))->get();

        $entries = collect();

        if (abs((float) $client->initial_balance) > 0.005) {
            $entries->push((object) [
                'date' => $client->created_at,
                'description' => __('Opening balance'),
                'debit' => max(0, (float) $client->initial_balance),
                'credit' => max(0, -(float) $client->initial_balance),
            ]);
        }

        foreach ($invoices as $invoice) {
            $entries->push((object) [
                'date' => $invoice->issue_date,
                'description' => __('Invoice :number', ['number' => $invoice->invoice_number]),
                'debit' => (float) $invoice->total,
                'credit' => 0,
            ]);
        }

        foreach ($payments as $payment) {
            $entries->push((object) [
                'date' => $payment->paid_at,
                'description' => __('Payment received'),
                'debit' => 0,
                'credit' => (float) $payment->amount,
            ]);
        }

        $balance = 0.0;

        return $entries->sortBy('date')->values()->map(function ($entry) use (&$balance) {
            $balance += $entry->debit - $entry->credit;
            $entry->balance = $balance;

            return $entry;
        });
    }
}
