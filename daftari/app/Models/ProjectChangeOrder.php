<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectChangeOrder extends Model
{
    public const STATUSES = ['draft', 'approved', 'rejected'];

    protected $fillable = [
        'project_id', 'change_order_number', 'title', 'description', 'amount',
        'cost_impact', 'days_impact', 'status', 'reviewed_by', 'reviewed_at', 'created_by',
    ];

    protected function casts(): array
    {
        return ['reviewed_at' => 'datetime'];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
