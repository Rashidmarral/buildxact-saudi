<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Client;
use App\Models\CompanyLetter;
use App\Models\MachineryAsset;
use App\Models\MachineryRentalContract;
use App\Models\Project;
use App\Models\Supplier;
use App\Services\MpdfRenderer;
use App\Support\LetterPresets;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * The Letters & Agreements generator — sale/purchase/rental agreements,
 * work handover letters, or any custom letter. LetterPresets supplies
 * starter bilingual content for the pre-defined kinds; content is a
 * freely editable ordered array of paragraph blocks from there on, and a
 * brand new kind of letter never needs a code change (document_type is a
 * free string — see the company_letters migration).
 */
class CompanyLetterController extends Controller
{
    public function index()
    {
        $letters = CompanyLetter::with('machinery', 'project')->orderByDesc('letter_date')->paginate(20);

        return view('user.machinery.letters.index', compact('letters'));
    }

    public function create(Request $request)
    {
        $documentType = $request->get('document_type', 'custom');
        $blueprint = LetterPresets::blueprint(in_array($documentType, LetterPresets::KINDS, true) ? $documentType : 'custom');

        $machinery = null;
        if ($machineryId = $request->integer('machinery_asset_id')) {
            $machinery = MachineryAsset::find($machineryId);
        }

        $rentalContract = null;
        if ($contractId = $request->integer('machinery_rental_contract_id')) {
            $rentalContract = MachineryRentalContract::find($contractId);
            $machinery = $machinery ?? $rentalContract?->machinery;
        }

        return view('user.machinery.letters.form', [
            'letter' => new CompanyLetter([
                'document_type' => $documentType,
                'title' => $blueprint['title'],
                'party_a_role' => $blueprint['party_a_role'],
                'party_b_role' => $blueprint['party_b_role'],
                'content' => $blueprint['content'],
                'language_mode' => 'bilingual',
                'letter_date' => now()->toDateString(),
                'machinery_asset_id' => $machinery?->id,
                'machinery_rental_contract_id' => $rentalContract?->id,
                'project_id' => $request->integer('project_id') ?: null,
            ]),
            'kinds' => LetterPresets::KINDS,
            'machinery' => MachineryAsset::orderBy('name')->get(),
            'clients' => Client::orderBy('name')->get(),
            'suppliers' => Supplier::orderBy('name')->get(),
            'projects' => Project::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $company = Auth::user()->company;

        $letter = CompanyLetter::create($data + [
            'company_id' => $company->id,
            'reference_number' => $company->nextLetterNumber(),
            'created_by' => Auth::id(),
        ]);

        AuditLog::record('letter.create', $letter, __('Generated letter :number', ['number' => $letter->reference_number]));

        return redirect()->route('app.machinery.letters.show', $letter)->with('status', __('Letter generated.'));
    }

    public function show(CompanyLetter $letter)
    {
        $letter->load('machinery', 'rentalContract', 'project', 'client', 'supplier', 'attachments');

        return view('user.machinery.letters.show', compact('letter'));
    }

    public function edit(CompanyLetter $letter)
    {
        return view('user.machinery.letters.form', [
            'letter' => $letter,
            'kinds' => LetterPresets::KINDS,
            'machinery' => MachineryAsset::orderBy('name')->get(),
            'clients' => Client::orderBy('name')->get(),
            'suppliers' => Supplier::orderBy('name')->get(),
            'projects' => Project::orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, CompanyLetter $letter)
    {
        $letter->update($this->validated($request));

        return redirect()->route('app.machinery.letters.show', $letter)->with('status', __('Letter updated.'));
    }

    public function destroy(CompanyLetter $letter)
    {
        $letter->delete();

        return redirect()->route('app.machinery.letters.index')->with('status', __('Letter deleted.'));
    }

    public function pdf(CompanyLetter $letter, MpdfRenderer $renderer)
    {
        $letter->load('machinery', 'project', 'client', 'supplier');

        $pdf = $renderer->render('documents.print.letter-pdf', [
            'title' => $letter->title,
            'subject' => $letter->reference_number,
            'company' => $letter->company,
            'template' => $letter->company->defaultTemplateFor('letter'),
            'letter' => $letter,
        ]);

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.Str::slug($letter->reference_number).'.pdf"',
        ]);
    }

    private function validated(Request $request): array
    {
        $companyId = Auth::user()->company_id;

        $data = $request->validate([
            'machinery_asset_id' => ['nullable', Rule::exists('machinery_assets', 'id')->where('company_id', $companyId)],
            'machinery_rental_contract_id' => ['nullable', Rule::exists('machinery_rental_contracts', 'id')->where('company_id', $companyId)],
            'project_id' => ['nullable', Rule::exists('projects', 'id')->where('company_id', $companyId)],
            'document_type' => ['required', 'string', 'max:40'],
            'title' => ['required', 'string', 'max:255'],
            'letter_date' => ['required', 'date'],
            'party_a_role' => ['required', 'string', 'max:60'],
            'party_a_name' => ['nullable', 'string', 'max:255'],
            'party_b_role' => ['required', 'string', 'max:60'],
            'party_b_name' => ['required', 'string', 'max:255'],
            'party_b_details' => ['nullable', 'string', 'max:2000'],
            'client_id' => ['nullable', Rule::exists('clients', 'id')->where('company_id', $companyId)],
            'supplier_id' => ['nullable', Rule::exists('suppliers', 'id')->where('company_id', $companyId)],
            'language_mode' => ['required', Rule::in(['bilingual', 'english_only', 'arabic_only'])],
            'content' => ['required', 'array', 'min:1'],
            'content.*.text_en' => ['nullable', 'string', 'max:5000'],
            'content.*.text_ar' => ['nullable', 'string', 'max:5000'],
            'content.*.is_heading' => ['nullable', 'boolean'],
        ]);

        // array_values() re-sequences the surviving blocks into a clean
        // 0..n-1 list — the browser's field indices (content[3][...],
        // content[7][...], ...) are just unique identifiers, not meant to
        // reach storage as gapped array keys (which would otherwise
        // json_encode() as a JSON object instead of an array).
        $data['content'] = array_values(array_map(fn ($block) => [
            'text_en' => $block['text_en'] ?? '',
            'text_ar' => $block['text_ar'] ?? '',
            'is_heading' => (bool) ($block['is_heading'] ?? false),
        ], $data['content']));

        return $data;
    }
}
