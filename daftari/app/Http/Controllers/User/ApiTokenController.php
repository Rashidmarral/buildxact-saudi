<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Services\Features\FeatureAccessService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ApiTokenController extends Controller
{
    public function index()
    {
        $tokens = Auth::user()->tokens()->latest()->get();

        return view('user.settings.api-tokens', compact('tokens'));
    }

    /**
     * Days-until-expiry options offered on the form; 'never' keeps
     * Sanctum's default (no expires_at) for anyone who genuinely needs a
     * long-lived integration token — the point is offering a shorter
     * lifetime, not forcing one.
     */
    private const EXPIRY_OPTIONS = ['30', '90', '365', 'never'];

    public function store(Request $request, FeatureAccessService $featureAccess)
    {
        if (! $featureAccess->enabled(Auth::user()->company, 'api')) {
            return back()->withErrors([
                'feature' => __("This feature isn't included in your current plan. Upgrade your plan to unlock it."),
            ]);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'access' => ['required', 'in:read,write'],
            'expires_in' => ['required', 'in:'.implode(',', self::EXPIRY_OPTIONS)],
        ]);

        $abilities = $data['access'] === 'write' ? ['read', 'write'] : ['read'];
        $expiresAt = $data['expires_in'] === 'never' ? null : now()->addDays((int) $data['expires_in']);

        $token = Auth::user()->createToken($data['name'], $abilities, $expiresAt);

        AuditLog::record('user.api_token_created', Auth::user(), __('Created API token :name', ['name' => $data['name']]));

        return view('user.settings.api-token-created', ['plainTextToken' => $token->plainTextToken]);
    }

    public function destroy(Request $request, int $tokenId)
    {
        $token = Auth::user()->tokens()->where('id', $tokenId)->firstOrFail();
        $name = $token->name;
        $token->delete();

        AuditLog::record('user.api_token_revoked', Auth::user(), __('Revoked API token :name', ['name' => $name]));

        return redirect()->route('app.settings.api-tokens')->with('status', __('API token revoked.'));
    }
}
