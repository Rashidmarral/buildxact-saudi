<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One editable week of the operator's own 90-day new-customer sales plan.
 * See database/migrations/..._create_sales_targets_table.php — this model
 * is deliberately goal-only; SalesCenterController computes each week's
 * actual counts live from Lead/Partner/Payment rather than storing them
 * here.
 */
class SalesTarget extends Model
{
    public const WEEKS = 13;

    protected $fillable = [
        'week_number', 'new_leads_target', 'demos_target', 'won_target',
    ];
}
