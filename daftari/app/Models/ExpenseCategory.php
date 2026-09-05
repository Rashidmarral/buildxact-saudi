<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExpenseCategory extends Model
{
    use BelongsToCompany;

    protected $fillable = ['company_id', 'name', 'name_ar'];

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }
}
