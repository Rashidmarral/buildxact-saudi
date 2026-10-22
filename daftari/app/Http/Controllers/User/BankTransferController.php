<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Http\Controllers\User\Concerns\EnforcesStorageQuota;
use App\Http\Controllers\User\Concerns\ResolvesPerPage;
use App\Models\Attachment;
use App\Models\AuditLog;
use App\Models\BankAccount;
use App\Models\BankTransfer;
use App\Models\Project;
use App\Services\Accounting\LedgerPostingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class BankTransferController extends Controller
{
    use EnforcesStorageQuota, ResolvesPerPage;

    public function index(Request $request)
    {
        $transfers = BankTransfer::with('fromAccount', 'toAccount')->latest('date')->latest('id')->paginate($this->resolvePerPage($request))->withQueryString();

        return view('user.bank-transfers.index', compact('transfers'));
    }

    public function show(BankTransfer $bankTransfer)
    {
        $bankTransfer->load('fromAccount', 'toAccount', 'project', 'attachments');

        return view('user.bank-transfers.show', ['transfer' => $bankTransfer]);
    }

    public function create(Request $request)
    {
        return view('user.bank-transfers.form', [
            'accounts' => BankAccount::where('is_active', true)->orderBy('name')->get(),
            'projects' => Project::orderBy('name')->get(),
            // Prefilled from the Cash Custody page's "Give advance" /
            // "Return cash" quick-action links, so the right custodian
            // account is already picked instead of the user hunting for
            // it in a long dropdown.
            'prefillFrom' => $request->integer('from_bank_account_id') ?: null,
            'prefillTo' => $request->integer('to_bank_account_id') ?: null,
        ]);
    }

    public function store(Request $request, LedgerPostingService $ledger)
    {
        $data = $this->validated($request);

        $transfer = DB::transaction(function () use ($data, $ledger) {
            $transfer = BankTransfer::create($data + ['created_by' => Auth::id(), 'status' => 'issued']);
            $ledger->postBankTransfer($transfer);

            return $transfer;
        });

        AuditLog::record('bank_transfer.create', $transfer, __('Recorded transfer of :amount from :from to :to', [
            'amount' => $transfer->amount, 'from' => $transfer->fromAccount->name, 'to' => $transfer->toAccount->name,
        ]));

        return redirect()->route('app.bank-transfers.index')->with('status', __('Transfer recorded.'));
    }

    /**
     * Editing is only safe while the transfer is still live — once voided,
     * fixing it means recording a fresh, correct transfer instead (mirrors
     * the same rule already applied to Payment/Receipt Vouchers).
     */
    public function edit(BankTransfer $bankTransfer)
    {
        if ($bankTransfer->status === 'void') {
            return redirect()->route('app.bank-transfers.show', $bankTransfer)->with('status', __('A voided transfer cannot be edited — record a new one instead.'));
        }

        return view('user.bank-transfers.form', [
            'transfer' => $bankTransfer,
            'accounts' => BankAccount::where('is_active', true)->orderBy('name')->get(),
            'projects' => Project::orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, BankTransfer $bankTransfer, LedgerPostingService $ledger)
    {
        if ($bankTransfer->status === 'void') {
            return back()->with('status', __('A voided transfer cannot be edited — record a new one instead.'));
        }

        $data = $this->validated($request);
        $old = $bankTransfer->only(['from_bank_account_id', 'to_bank_account_id', 'amount', 'date']);

        DB::transaction(function () use ($data, $bankTransfer, $ledger) {
            // Rebuild the ledger posting from scratch rather than adjusting
            // it in place — postBankTransfer() derives both lines from the
            // transfer's current state (and may legitimately post nothing
            // at all for a same-type transfer), so deleting the old entry
            // first is the only way to avoid a stale or doubled posting.
            $ledger->deletePosting($bankTransfer->company, 'bank_transfer', $bankTransfer->id);

            $bankTransfer->update($data);

            $ledger->postBankTransfer($bankTransfer->fresh(['fromAccount', 'toAccount']));
        });

        AuditLog::record('bank_transfer.update', $bankTransfer, __('Updated transfer'), old: $old, new: $data);

        return redirect()->route('app.bank-transfers.show', $bankTransfer)->with('status', __('Transfer updated.'));
    }

    /**
     * Reverses the transfer's ledger entry (if any) and excludes it from
     * every balance/report query from this point on, but keeps the row
     * itself as a visible, audit-logged record of the correction — the
     * same void-before-delete pattern used for Payment/Receipt Vouchers.
     */
    public function void(BankTransfer $bankTransfer, LedgerPostingService $ledger)
    {
        if ($bankTransfer->status === 'void') {
            return back();
        }

        DB::transaction(function () use ($bankTransfer, $ledger) {
            $bankTransfer->update(['status' => 'void']);
            $ledger->reverse($bankTransfer->company, 'bank_transfer', $bankTransfer->id, __('Transfer :from -> :to voided', [
                'from' => $bankTransfer->fromAccount->name, 'to' => $bankTransfer->toAccount->name,
            ]));
        });

        AuditLog::record('bank_transfer.void', $bankTransfer, __('Voided transfer of :amount from :from to :to', [
            'amount' => $bankTransfer->amount, 'from' => $bankTransfer->fromAccount->name, 'to' => $bankTransfer->toAccount->name,
        ]));

        return back()->with('status', __('Transfer voided.'));
    }

    public function destroy(BankTransfer $bankTransfer)
    {
        if ($bankTransfer->status !== 'void') {
            return back()->with('status', __('Void this transfer first so its ledger entry is safely reversed, then delete it.'));
        }

        AuditLog::record('bank_transfer.delete', $bankTransfer, __('Deleted voided transfer of :amount from :from to :to', [
            'amount' => $bankTransfer->amount, 'from' => $bankTransfer->fromAccount->name, 'to' => $bankTransfer->toAccount->name,
        ]));

        $bankTransfer->delete();

        return redirect()->route('app.bank-transfers.index')->with('status', __('Transfer deleted.'));
    }

    private function validated(Request $request): array
    {
        $companyId = Auth::user()->company_id;

        return $request->validate([
            'from_bank_account_id' => ['required', 'different:to_bank_account_id', Rule::exists('bank_accounts', 'id')->where('company_id', $companyId)],
            'to_bank_account_id' => ['required', Rule::exists('bank_accounts', 'id')->where('company_id', $companyId)],
            'project_id' => ['nullable', Rule::exists('projects', 'id')->where('company_id', $companyId)],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
    }

    public function storeAttachment(Request $request, BankTransfer $bankTransfer)
    {
        if ($rejected = $this->rejectIfStorageQuotaReached($bankTransfer->company)) {
            return $rejected;
        }

        $request->validate(['file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,gif,webp,doc,docx,xls,xlsx,csv,txt', 'max:10240']]);

        $file = $request->file('file');

        $bankTransfer->attachments()->create([
            'company_id' => $bankTransfer->company_id,
            'uploaded_by' => Auth::id(),
            'original_name' => $file->getClientOriginalName(),
            'path' => $file->store('bank-transfer-attachments', 'public'),
            'size' => $file->getSize(),
            'mime_type' => $file->getMimeType(),
        ]);

        return back()->with('status', __('File attached.'));
    }

    public function destroyAttachment(BankTransfer $bankTransfer, Attachment $attachment)
    {
        abort_unless($attachment->attachable_type === BankTransfer::class && $attachment->attachable_id === $bankTransfer->id, 404);

        Storage::disk('public')->delete($attachment->path);
        $attachment->delete();

        return back()->with('status', __('Attachment removed.'));
    }
}
