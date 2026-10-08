<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Http\Controllers\User\Concerns\EnforcesStorageQuota;
use App\Http\Controllers\User\Concerns\ResolvesPerPage;
use App\Models\Attachment;
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

    public function create()
    {
        return view('user.bank-transfers.form', [
            'accounts' => BankAccount::where('is_active', true)->orderBy('name')->get(),
            'projects' => Project::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request, LedgerPostingService $ledger)
    {
        $companyId = Auth::user()->company_id;

        $data = $request->validate([
            'from_bank_account_id' => ['required', 'different:to_bank_account_id', Rule::exists('bank_accounts', 'id')->where('company_id', $companyId)],
            'to_bank_account_id' => ['required', Rule::exists('bank_accounts', 'id')->where('company_id', $companyId)],
            'project_id' => ['nullable', Rule::exists('projects', 'id')->where('company_id', $companyId)],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $transfer = DB::transaction(function () use ($data, $ledger) {
            $transfer = BankTransfer::create($data + ['created_by' => Auth::id()]);
            $ledger->postBankTransfer($transfer);

            return $transfer;
        });

        return redirect()->route('app.bank-transfers.index')->with('status', __('Transfer recorded.'));
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
