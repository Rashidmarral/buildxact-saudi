<?php

namespace App\Http\Controllers\User;

/**
 * "we need to separate project manager from subcontractor is other
 * thing... make it completely separate from subcontractor like add
 * nav link or something in project module" — cash given to an
 * employee/project manager who then manages a work package themselves
 * (Shahid's situation: advanced 167,800 so far against a 373,650
 * budget, spent against cost categories like Sub Base/Asphalt) needs
 * exactly the same budget/BOQ/category-breakdown/voucher-linking
 * machinery a Subcontract already gives a Supplier — see market
 * research on Procore/CoConstruct/Buildxact: they track this as
 * "Direct Costs" against the same cost-code budget a "Commitment"
 * (subcontractor contract) also posts to, not as an unrelated ledger.
 * This reuses that same Subcontract/BoqItem/ReportCategory/Payment
 * data layer (DRY — see SubcontractController's four overridable
 * hooks), but is a genuinely separate module end to end: its own
 * nav link, its own URL prefix, its own pages, and — the actual
 * separation the user asked for — it can never be created against a
 * Supplier, and the Subcontracts module can never be created against
 * an Employee.
 *
 * "do you now want a 5% retention and a milestone-style schedule to
 * appear for the Project Manager Budget too... Yes — add 5% retention
 * and a milestone schedule to PM budgets" — an explicit reversal of
 * this module's original "no retention % or milestone schedule, since
 * those are contractual concepts that don't apply to cash given to
 * your own staff" decision: a Project Manager Budget now gets the
 * exact same milestone/retention machinery a Subcontract gives a
 * Supplier (syncsMilestones() is no longer overridden, so it inherits
 * the parent's `true`), alongside the budget's own "detail wise"
 * breakdown — the BOQ items (what the total budget is made of) and
 * the report categories (what's actually been spent against) — both
 * already built for Subcontracts and reused as-is.
 */
class ProjectManagerBudgetController extends SubcontractController
{
    protected function partyTypeScope(): string
    {
        return 'employee';
    }

    protected function routeName(string $action): string
    {
        return 'app.project-manager-budgets.'.$action;
    }

    protected function viewName(string $view): string
    {
        return 'user.project-manager-budgets.'.$view;
    }
}
