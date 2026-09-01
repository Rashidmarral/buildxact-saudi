<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Attachment;
use App\Models\BankAccount;
use App\Models\Branch;
use App\Models\Company;
use App\Rules\SaudiCrNumber;
use App\Rules\SaudiPhoneNumber;
use App\Rules\SaudiVatNumber;
use App\Support\UserAgentSummary;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class SettingsController extends Controller
{
    /**
     * Company compliance documents commonly required in Saudi Arabia. Not
     * all are mandatory for every business (e.g. Zakat certificate only
     * applies to Saudi/GCC-owned companies), so these are offered as
     * labeled options rather than enforced — the readiness checklist
     * covers what ZATCA itself actually requires.
     */
    public const DOCUMENT_TYPES = [
        'cr' => 'Commercial Registration (CR)',
        'vat_certificate' => 'VAT Certificate',
        'national_address_certificate' => 'National Address Certificate',
        'zakat_certificate' => 'Zakat / Tax Certificate',
        'municipality_license' => 'Municipality License',
        'chamber_of_commerce' => 'Chamber of Commerce Membership',
        'gosi_certificate' => 'GOSI Certificate',
        'other' => 'Other',
    ];

    public function index()
    {
        $company = Auth::user()->company;
        $branches = Branch::orderBy('name')->get();
        $bankAccounts = BankAccount::where('is_active', true)->orderBy('name')->get();
        $documents = $company->documents;
        $sessions = $this->activeSessions();

        return view('user.settings.index', compact('company', 'branches', 'bankAccounts', 'documents', 'sessions'));
    }

    /**
     * Only meaningful on the "database" session driver — other drivers
     * (file, redis, etc.) don't expose a queryable list of active sessions,
     * so the view falls back to a plain explanation instead of a table.
     */
    protected function activeSessions()
    {
        if (config('session.driver') !== 'database') {
            return null;
        }

        return DB::table('sessions')
            ->where('user_id', Auth::id())
            ->orderByDesc('last_activity')
            ->get()
            ->map(function ($session) {
                $session->is_current_device = $session->id === session()->getId();
                $session->device = UserAgentSummary::describe($session->user_agent);
                $session->last_active = Carbon::createFromTimestamp($session->last_activity);

                return $session;
            });
    }

    public function update(Request $request)
    {
        $companyId = Auth::user()->company_id;

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'name_ar' => ['nullable', 'string', 'max:255'],
            'vat_number' => ['nullable', 'string', new SaudiVatNumber],
            'cr_number' => ['nullable', 'string', new SaudiCrNumber],
            'alternative_seller_id_type' => ['nullable', 'in:commercial_registration,momra_license,mhrsd_license,passport_number,gcc_id,other_id'],
            'alternative_seller_id' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'building_number' => ['nullable', 'string', 'max:20'],
            'street_name' => ['nullable', 'string', 'max:255'],
            'district' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'additional_number' => ['nullable', 'string', 'max:20'],
            'phone' => ['nullable', 'string', new SaudiPhoneNumber],
            'email' => ['nullable', 'email', 'max:255'],
            'invoice_prefix' => ['required', 'string', 'max:10'],
            'default_payment_terms_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'primary_customer_type' => ['required', 'in:b2b,b2c,mixed'],
            'negative_number_format' => ['required', 'in:minus,parentheses'],
            'default_branch_id' => ['nullable', Rule::exists('branches', 'id')->where('company_id', $companyId)],
            'default_bank_account_id' => ['nullable', Rule::exists('bank_accounts', 'id')->where('company_id', $companyId)],
            'logo' => ['nullable', 'image', 'max:2048'],
            'stamp' => ['nullable', 'image', 'max:2048'],
        ]);

        $company = Auth::user()->company;
        unset($data['logo'], $data['stamp']);

        if ($request->hasFile('logo')) {
            if ($company->logo_path) {
                Storage::disk('public')->delete($company->logo_path);
            }
            $data['logo_path'] = $request->file('logo')->store('logos', 'public');
        }

        if ($request->hasFile('stamp') && $company->hasFeature('stamps')) {
            if ($company->stamp_path) {
                Storage::disk('public')->delete($company->stamp_path);
            }
            $data['stamp_path'] = $request->file('stamp')->store('stamps', 'public');
        }

        $company->update($data);

        return back()->with('status', __('Settings saved.'));
    }

    public function storeDocument(Request $request)
    {
        $data = $request->validate([
            'document_type' => ['required', Rule::in(array_keys(self::DOCUMENT_TYPES))],
            'expiry_date' => ['nullable', 'date'],
            'file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ]);

        $company = Auth::user()->company;
        $file = $data['file'];
        $path = $file->store('company-documents', 'public');

        Attachment::create([
            'company_id' => $company->id,
            'attachable_type' => Company::class,
            'attachable_id' => $company->id,
            'uploaded_by' => Auth::id(),
            'original_name' => $file->getClientOriginalName(),
            'path' => $path,
            'size' => $file->getSize(),
            'mime_type' => $file->getClientMimeType(),
            'document_type' => $data['document_type'],
            'expiry_date' => $data['expiry_date'] ?? null,
        ]);

        return back()->with('status', __('Document uploaded.'));
    }

    public function destroyDocument(Attachment $attachment)
    {
        abort_if($attachment->company_id !== Auth::user()->company_id, 404);

        Storage::disk('public')->delete($attachment->path);
        $attachment->delete();

        return back()->with('status', __('Document removed.'));
    }

    public function updatePassword(Request $request)
    {
        $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $user = Auth::user();

        if (! Hash::check($request->string('current_password'), $user->password)) {
            return back()->withErrors(['current_password' => __('The current password is incorrect.')]);
        }

        $user->update(['password' => Hash::make($request->string('password'))]);

        return back()->with('status', __('Password updated.'));
    }

    /**
     * Revokes every other session belonging to this user, keeping the one
     * making this request alive. Requires the current password first —
     * matching the change-password form above — so that anyone with brief
     * access to an already-open, unlocked browser can't silently kick the
     * real owner's other devices without knowing their password.
     */
    public function logoutOtherSessions(Request $request)
    {
        $request->validate(['current_password' => ['required', 'string']]);

        $user = Auth::user();

        if (! Hash::check($request->string('current_password'), $user->password)) {
            return back()->withErrors(['sessions_current_password' => __('The current password is incorrect.')]);
        }

        if (config('session.driver') === 'database') {
            DB::table('sessions')
                ->where('user_id', $user->id)
                ->where('id', '!=', session()->getId())
                ->delete();
        }

        return back()->with('status', __('Logged out of all other sessions.'));
    }

    public function destroySession(Request $request, string $sessionId)
    {
        abort_unless(config('session.driver') === 'database', 404);
        abort_if($sessionId === session()->getId(), 422);

        DB::table('sessions')
            ->where('user_id', Auth::id())
            ->where('id', $sessionId)
            ->delete();

        return back()->with('status', __('Session logged out.'));
    }
}
