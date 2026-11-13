<?php

namespace App\Http\Controllers\User\CoffeeShop;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\LoyaltyCard;
use App\Services\CoffeeShop\LoyaltyCardService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use RuntimeException;

class LoyaltyCardController extends Controller
{
    public function index()
    {
        return view('user.coffee-shop.loyalty-cards.index', [
            'cards' => LoyaltyCard::with('client')->latest()->get(),
            'clients' => Client::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request, LoyaltyCardService $service)
    {
        $companyId = Auth::user()->company_id;

        $data = $request->validate([
            'client_id' => ['nullable', Rule::exists('clients', 'id')->where('company_id', $companyId)],
            'initial_top_up' => ['nullable', 'numeric', 'min:0'],
        ]);

        $card = $service->createCard(Auth::user()->company, $data['client_id'] ?? null, (float) ($data['initial_top_up'] ?? 0));

        return back()->with('status', __('Card :number created.', ['number' => $card->card_number]));
    }

    public function topUp(Request $request, LoyaltyCard $card, LoyaltyCardService $service)
    {
        $data = $request->validate(['amount' => ['required', 'numeric', 'min:0.01']]);

        try {
            $service->topUp($card, (float) $data['amount']);
        } catch (RuntimeException $e) {
            return back()->withErrors(['card' => $e->getMessage()]);
        }

        return back()->with('status', __('Card :number topped up.', ['number' => $card->card_number]));
    }

    /**
     * Card lookup during checkout — mirrors the item-lookup pattern used by
     * Restaurant/Repair Shop, scoped to active cards for this company only.
     */
    public function lookup(Request $request)
    {
        $query = trim((string) $request->query('q'));

        $card = LoyaltyCard::where('is_active', true)
            ->where('card_number', $query)
            ->first();

        if (! $card) {
            return response()->json(['found' => false]);
        }

        return response()->json([
            'found' => true,
            'id' => $card->id,
            'card_number' => $card->card_number,
            'balance' => (float) $card->balance,
        ]);
    }
}
