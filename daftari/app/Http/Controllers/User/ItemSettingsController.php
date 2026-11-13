<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * "barcode/SKU settings (prefix, auto-generate, length)" — a Hamly
 * settings gap. Manual SKU/barcode entry on the item form (and the
 * existing random-barcode "Generate" button) keeps working exactly as
 * before regardless of these settings; sku_auto_generate only changes
 * what happens when the SKU field is left blank on a new item (see
 * ItemController::store()/generateSku()), and barcode_length only
 * changes how many digits ItemController::generateBarcode() hands out.
 */
class ItemSettingsController extends Controller
{
    public function show()
    {
        return view('user.settings.barcode-sku', [
            'company' => Auth::user()->company,
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'sku_prefix' => ['nullable', 'string', 'max:20'],
            'sku_auto_generate' => ['nullable', 'boolean'],
            'barcode_length' => ['required', 'integer', 'min:6', 'max:18'],
        ]);

        Auth::user()->company->update([
            'sku_prefix' => $data['sku_prefix'] ?? null,
            'sku_auto_generate' => $request->boolean('sku_auto_generate'),
            'barcode_length' => $data['barcode_length'],
        ]);

        AuditLog::record('company.barcode_sku_settings_update', null, __('Updated barcode/SKU settings'));

        return back()->with('status', __('Barcode & SKU settings saved.'));
    }
}
