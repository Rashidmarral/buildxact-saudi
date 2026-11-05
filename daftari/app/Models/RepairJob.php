<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RepairJob extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'job_number', 'client_id', 'item_description', 'brand', 'model', 'year', 'serial_or_plate',
        'issue_description', 'diagnosis_notes', 'status', 'approved_at', 'approved_by',
        'pos_sale_id', 'created_by', 'cancel_reason', 'completed_at',
    ];

    protected function casts(): array
    {
        return ['approved_at' => 'datetime', 'completed_at' => 'datetime'];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(RepairJobItem::class);
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(PosSale::class, 'pos_sale_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function isOpen(): bool
    {
        return ! in_array($this->status, ['collected', 'cancelled'], true);
    }

    public function isApproved(): bool
    {
        return $this->approved_at !== null;
    }
}
