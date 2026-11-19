<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FxRevaluation extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'as_of_date', 'journal_entry_id', 'reversed_at', 'created_by', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'as_of_date' => 'date',
            'reversed_at' => 'datetime',
        ];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(FxRevaluationLine::class);
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isActive(): bool
    {
        return $this->reversed_at === null;
    }
}
