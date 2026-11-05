<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Services\WathqService;
use Illuminate\Http\Request;

/**
 * JSON endpoint behind the "Verify via Wathq" button on the Client and
 * Supplier forms — see WathqService for the actual lookup and
 * resources/views/user/clients|suppliers/form.blade.php for the Alpine
 * code that calls this and fills in the form fields on success.
 */
class WathqLookupController extends Controller
{
    public function commercialRegistration(Request $request, WathqService $wathq)
    {
        $data = $request->validate([
            'cr_number' => ['required', 'string', 'max:50'],
        ]);

        $result = $wathq->lookupCommercialRegistration($data['cr_number']);

        return response()->json($result, $result['success'] ? 200 : 422);
    }

    public function nationalAddress(Request $request, WathqService $wathq)
    {
        $data = $request->validate([
            'short_address' => ['required', 'string', 'max:10'],
        ]);

        $result = $wathq->lookupNationalAddress($data['short_address']);

        return response()->json($result, $result['success'] ? 200 : 422);
    }
}
