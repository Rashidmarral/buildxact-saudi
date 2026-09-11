<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\LegalDocument;
use App\Models\PlatformActivity;
use App\Models\Setting;
use App\Support\PlatformBranding;
use Illuminate\Http\Request;

/**
 * "Are we actually allowed to sell this?" — a plain checklist, not an
 * automated compliance guarantee. Every check here surfaces a real
 * question for the operator's own business/legal/tax advisor to answer;
 * none of them certify anything on Daftari's behalf. See PlatformActivity
 * for the CR-activity heuristic and the ZATCA disclaimer legal document
 * for the fuller explanation of what "ZATCA-compliant" does and doesn't
 * mean here.
 */
class ComplianceController extends Controller
{
    public function index()
    {
        $branding = PlatformBranding::all();
        $activities = PlatformActivity::orderBy('sort_order')->get();
        $documents = LegalDocument::orderBy('sort_order')->get();

        return view('admin.compliance.index', [
            'identityConfigured' => filled($branding['name']) && filled($branding['vat_number']) && filled($branding['cr_number']) && filled($branding['address']),
            'branding' => $branding,
            'activities' => $activities,
            'activitiesSeemToCoverSoftware' => PlatformActivity::seemsToCoverSoftwareSales(),
            'zatcaCertified' => Setting::getBool('platform_zatca_certified'),
            'zatcaCertificationNote' => Setting::get('platform_zatca_certification_note'),
            'documents' => $documents,
        ]);
    }

    public function storeActivity(Request $request)
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:20'],
            'description_ar' => ['required', 'string', 'max:500'],
            'description_en' => ['nullable', 'string', 'max:500'],
        ]);

        $maxOrder = (int) PlatformActivity::max('sort_order');

        PlatformActivity::create([
            'code' => $data['code'],
            'description_ar' => $data['description_ar'],
            'description_en' => $data['description_en'] ?? null,
            'sort_order' => $maxOrder + 1,
        ]);

        AuditLog::record('compliance.activity_added', null, __('Added a registered CR activity: :code', ['code' => $data['code']]));

        return back()->with('status', __('Activity added.'));
    }

    public function destroyActivity(PlatformActivity $platformActivity)
    {
        $platformActivity->delete();

        AuditLog::record('compliance.activity_removed', null, __('Removed a registered CR activity: :code', ['code' => $platformActivity->code]));

        return back()->with('status', __('Activity removed.'));
    }

    public function updateZatcaStatus(Request $request)
    {
        $data = $request->validate([
            'certified' => ['nullable', 'boolean'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        Setting::set('platform_zatca_certified', $request->boolean('certified') ? '1' : '0');
        Setting::set('platform_zatca_certification_note', $data['note'] ?? '');

        AuditLog::record('compliance.zatca_status_update', null, __('Updated ZATCA certification status'));

        return back()->with('status', __('ZATCA certification status saved.'));
    }
}
