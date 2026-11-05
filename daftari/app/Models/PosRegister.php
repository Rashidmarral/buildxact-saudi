<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PosRegister extends Model
{
    use BelongsToCompany;

    protected $fillable = ['company_id', 'branch_id', 'warehouse_id', 'name', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function shifts(): HasMany
    {
        return $this->hasMany(PosShift::class, 'register_id');
    }

    public function openShift(): ?PosShift
    {
        return $this->shifts()->where('status', 'open')->latest('opened_at')->first();
    }
}
