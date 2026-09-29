<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class MachineryRentalContract extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'machinery_asset_id', 'client_id', 'renter_name', 'renter_phone',
        'contract_number', 'start_date', 'end_date', 'rate', 'rate_type', 'deposit_amount',
        'operator_included', 'operator_employee_id', 'fuel_responsibility', 'project_id',
        'delivery_condition_notes', 'return_condition_notes', 'status', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'operator_included' => 'boolean',
            'rate' => 'decimal:2',
            'deposit_amount' => 'decimal:2',
        ];
    }

    public function machinery(): BelongsTo
    {
        return $this->belongsTo(MachineryAsset::class, 'machinery_asset_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function operator(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'operator_employee_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function letters(): HasMany
    {
        return $this->hasMany(CompanyLetter::class, 'machinery_rental_contract_id');
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    public function renterDisplayName(): string
    {
        return $this->client?->display_name ?? $this->renter_name ?? __('Unnamed renter');
    }
}
