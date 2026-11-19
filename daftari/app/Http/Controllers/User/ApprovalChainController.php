<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\ApprovalChainStep;
use App\Models\AuditLog;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/**
 * Configures the optional multi-tier approval chains layered on top of the
 * flat po_approval_threshold/expense_approval_threshold gate (see
 * ApprovalSettingsController and ApprovalChainService). A document type
 * with no steps here keeps behaving exactly like before this feature
 * existed; adding even one step here takes that document type out of the
 * flat-threshold gate entirely and hands it to the chain instead.
 */
class ApprovalChainController extends Controller
{
    public function store(Request $request)
    {
        $companyId = Auth::user()->company_id;

        $data = $request->validate([
            'document_type' => ['required', Rule::in(ApprovalChainStep::DOCUMENT_TYPES)],
            'role_id' => ['required', Rule::exists('roles', 'id')->where('company_id', $companyId)],
            'min_amount' => ['required', 'numeric', 'min:0'],
        ]);

        $nextStep = 1 + (int) ApprovalChainStep::where('document_type', $data['document_type'])->max('step_number');

        $step = ApprovalChainStep::create([
            'document_type' => $data['document_type'],
            'role_id' => $data['role_id'],
            'min_amount' => $data['min_amount'],
            'step_number' => $nextStep,
        ]);

        AuditLog::record('company.approval_chain_step_add', null, __('Added approval chain step :step for :type', ['step' => $nextStep, 'type' => $data['document_type']]));

        return back()->with('status', __('Approval step added.'));
    }

    public function destroy(ApprovalChainStep $approvalChainStep)
    {
        $documentType = $approvalChainStep->document_type;
        $removedStep = $approvalChainStep->step_number;
        $approvalChainStep->delete();

        // Steps after the removed one shift down so step_number stays a
        // contiguous 1..N sequence per document type — the chain's order
        // is what matters, not the specific numbers.
        ApprovalChainStep::where('document_type', $documentType)
            ->where('step_number', '>', $removedStep)
            ->orderBy('step_number')
            ->get()
            ->each(fn (ApprovalChainStep $step) => $step->update(['step_number' => $step->step_number - 1]));

        AuditLog::record('company.approval_chain_step_remove', null, __('Removed approval chain step for :type', ['type' => $documentType]));

        return back()->with('status', __('Approval step removed.'));
    }
}
