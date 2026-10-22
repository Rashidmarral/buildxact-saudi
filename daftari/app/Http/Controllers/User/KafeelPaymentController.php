<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Http\Controllers\User\Concerns\EnforcesStorageQuota;
use App\Http\Controllers\User\Concerns\ExportsCsv;
use App\Http\Controllers\User\Concerns\ResolvesPerPage;
use App\Http\Controllers\User\Concerns\ResolvesReportPeriod;
use App\Models\Attachment;
use App\Models\AuditLog;
use App\Models\KafeelPayment;
use App\Services\MpdfRenderer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * A private per-company log of payments made to/on behalf of a
 * company's Kafeel (sponsor) — see KafeelPayment's own docblock for
 * why this never touches the GL. The whole module is hidden unless a
 * Super Admin has switched it on for this exact company (see
 * FeatureRegistry's 'kafeel_payments' entry and the 'module:'
 * middleware on this controller's routes).
 */
class KafeelPaymentController extends Controller
{
    use EnforcesStorageQuota, ExportsCsv, ResolvesPerPage, ResolvesReportPeriod;

    public function index(Request $request)
    {
        $period = $this->resolvePeriod($request);

        $payments = KafeelPayment::with('creator')
            ->whereBetween('payment_date', [$period['from'], $period['to']])
            ->when($request->filled('category'), fn ($q) => $q->where('category', $request->query('category')))
            ->when($request->filled('search'), fn ($q) => $q->where(fn ($q2) => $q2
                ->where('description', 'like', '%'.$request->query('search').'%')
                ->orWhere('paid_to', 'like', '%'.$request->query('search').'%')))
            ->orderByDesc('payment_date')
            ->orderByDesc('id');

        $total = (clone $payments)->sum('amount');
        $count = (clone $payments)->count();
        $byCategory = (clone $payments)->get()->groupBy('category')->map(fn ($g) => $g->sum('amount'))->sortDesc();

        if ($request->query('export') === 'csv') {
            return $this->csvResponse('kafeel-payments.csv', [__('Date'), __('Paid to'), __('Category'), __('Description'), __('Amount'), __('Notes')],
                $payments->get()->map(fn (KafeelPayment $p) => [
                    $p->payment_date->format('Y-m-d'), $p->paid_to, $p->categoryLabel(), $p->description,
                    number_format((float) $p->amount, 2, '.', ''), $p->notes,
                ]));
        }

        if ($request->query('export') === 'pdf') {
            $pdf = app(MpdfRenderer::class)->render('user.kafeel-payments.pdf', [
                'company' => Auth::user()->company, 'period' => $period, 'payments' => $payments->get(),
                'total' => $total, 'byCategory' => $byCategory,
            ]);

            return response($pdf, 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="kafeel-payments.pdf"']);
        }

        return view('user.kafeel-payments.index', [
            'payments' => $payments->paginate($this->resolvePerPage($request))->withQueryString(),
            'period' => $period,
            'total' => $total,
            'count' => $count,
            'byCategory' => $byCategory,
            'allTimeTotal' => KafeelPayment::sum('amount'),
            'categories' => KafeelPayment::CATEGORIES,
        ]);
    }

    public function create()
    {
        return view('user.kafeel-payments.form', [
            'payment' => new KafeelPayment(['payment_date' => now()->toDateString()]),
            'categories' => KafeelPayment::CATEGORIES,
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['created_by'] = Auth::id();

        $payment = KafeelPayment::create($data);

        AuditLog::record('kafeel_payment.create', $payment, __('Recorded Kafeel payment of :amount to :to', ['amount' => \App\Support\Money::format($payment->amount), 'to' => $payment->paid_to ?: $payment->description]));

        return redirect()->route('app.kafeel-payments.edit', $payment)->with('status', __('Payment recorded.'));
    }

    public function edit(KafeelPayment $kafeelPayment)
    {
        $kafeelPayment->load('attachments');

        return view('user.kafeel-payments.form', [
            'payment' => $kafeelPayment,
            'categories' => KafeelPayment::CATEGORIES,
        ]);
    }

    public function update(Request $request, KafeelPayment $kafeelPayment)
    {
        $kafeelPayment->update($this->validated($request));

        return redirect()->route('app.kafeel-payments.edit', $kafeelPayment)->with('status', __('Payment updated.'));
    }

    public function destroy(KafeelPayment $kafeelPayment)
    {
        $kafeelPayment->delete();

        return redirect()->route('app.kafeel-payments.index')->with('status', __('Payment deleted.'));
    }

    public function storeAttachment(Request $request, KafeelPayment $kafeelPayment)
    {
        if ($rejected = $this->rejectIfStorageQuotaReached($kafeelPayment->company)) {
            return $rejected;
        }

        $request->validate(['file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,gif,webp', 'max:10240']]);

        $file = $request->file('file');

        $kafeelPayment->attachments()->create([
            'company_id' => $kafeelPayment->company_id,
            'uploaded_by' => Auth::id(),
            'original_name' => $file->getClientOriginalName(),
            'path' => $file->store('kafeel-payment-attachments', 'public'),
            'size' => $file->getSize(),
            'mime_type' => $file->getMimeType(),
        ]);

        return back()->with('status', __('File attached.'));
    }

    public function destroyAttachment(KafeelPayment $kafeelPayment, Attachment $attachment)
    {
        abort_unless($attachment->attachable_type === KafeelPayment::class && $attachment->attachable_id === $kafeelPayment->id, 404);

        Storage::disk('public')->delete($attachment->path);
        $attachment->delete();

        return back()->with('status', __('Attachment removed.'));
    }

    public function importForm()
    {
        return view('user.kafeel-payments.import', ['categories' => KafeelPayment::CATEGORIES]);
    }

    /**
     * Bulk-add historical payments from a spreadsheet (e.g. the Excel
     * sheet a company was already tracking these in before this module
     * existed) instead of re-typing every row one at a time. Columns are
     * matched by header name, case-insensitively, so the exact column
     * order doesn't matter — only Date/Description/Amount are required;
     * Paid To, Category, and Notes are optional and fall back sensibly
     * (an unrecognized or blank Category becomes 'other', never
     * guessed from the description — a human reviewing the imported
     * rows afterward can recategorize in a few clicks, which is safer
     * than a keyword heuristic silently misfiling something).
     */
    public function import(Request $request)
    {
        $request->validate(['file' => ['required', 'file', 'mimes:csv,txt', 'max:2048']]);

        $handle = fopen($request->file('file')->getRealPath(), 'r');
        $header = fgetcsv($handle);

        if (! $header) {
            fclose($handle);

            return back()->withErrors(['file' => __('The file is empty or not a valid CSV.')]);
        }

        $columns = array_map(fn ($h) => strtolower(trim((string) $h)), $header);
        $find = fn (array $names) => collect($names)->map(fn ($n) => array_search($n, $columns, true))->first(fn ($i) => $i !== false);

        $dateCol = $find(['date', 'payment date', 'payment_date']);
        $descCol = $find(['description', 'desc']);
        $amountCol = $find(['amount']);
        $paidToCol = $find(['paid to', 'paid_to', 'recipient']);
        $categoryCol = $find(['category']);
        $notesCol = $find(['notes', 'note']);

        if ($dateCol === null || $descCol === null || $amountCol === null) {
            fclose($handle);

            return back()->withErrors(['file' => __('The file must have at least Date, Description, and Amount columns.')]);
        }

        $imported = 0;
        $skipped = [];
        $rowNumber = 1;
        $validCategories = array_keys(KafeelPayment::CATEGORIES);

        while (($row = fgetcsv($handle)) !== false) {
            $rowNumber++;

            if (count(array_filter($row, fn ($v) => trim((string) $v) !== '')) === 0) {
                continue;
            }

            $dateRaw = trim((string) ($row[$dateCol] ?? ''));
            $date = $dateRaw !== '' ? strtotime($dateRaw) : false;
            $amountRaw = preg_replace('/[^\d.\-]/', '', (string) ($row[$amountCol] ?? ''));

            if (! $date || $amountRaw === '' || ! is_numeric($amountRaw) || (float) $amountRaw <= 0) {
                $skipped[] = __('Row :row: invalid date or amount.', ['row' => $rowNumber]);

                continue;
            }

            $category = $categoryCol !== null ? strtolower(trim((string) ($row[$categoryCol] ?? ''))) : '';
            $category = str_replace(' ', '_', $category);

            KafeelPayment::create([
                'payment_date' => date('Y-m-d', $date),
                'description' => trim((string) ($row[$descCol] ?? '')) ?: __('Kafeel payment'),
                'amount' => (float) $amountRaw,
                'paid_to' => $paidToCol !== null ? (trim((string) ($row[$paidToCol] ?? '')) ?: null) : null,
                'category' => in_array($category, $validCategories, true) ? $category : 'other',
                'notes' => $notesCol !== null ? (trim((string) ($row[$notesCol] ?? '')) ?: null) : null,
                'created_by' => Auth::id(),
            ]);
            $imported++;
        }

        fclose($handle);

        AuditLog::record('kafeel_payment.import', null, __(':count Kafeel payments imported from CSV.', ['count' => $imported]));

        $status = __(':count payment(s) imported.', ['count' => $imported]);
        if ($skipped) {
            $status .= ' '.__(':count row(s) skipped.', ['count' => count($skipped)]);
        }

        return redirect()->route('app.kafeel-payments.index')->with('status', $status)->with('importSkipped', $skipped);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'payment_date' => ['required', 'date'],
            'paid_to' => ['nullable', 'string', 'max:150'],
            'category' => ['required', Rule::in(array_keys(KafeelPayment::CATEGORIES))],
            'description' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
    }
}
