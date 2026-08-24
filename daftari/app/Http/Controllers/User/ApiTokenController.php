<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ApiTokenController extends Controller
{
    public function index()
    {
        $tokens = Auth::user()->tokens()->latest()->get();

        return view('user.settings.api-tokens', compact('tokens'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'access' => ['required', 'in:read,write'],
        ]);

        $abilities = $data['access'] === 'write' ? ['read', 'write'] : ['read'];

        $token = Auth::user()->createToken($data['name'], $abilities);

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
