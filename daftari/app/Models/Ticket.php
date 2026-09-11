<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ticket extends Model
{
    use BelongsToCompany;

    public const PRIORITIES = ['low', 'normal', 'high', 'urgent'];

    public const STATUSES = ['open', 'in_progress', 'waiting_customer', 'resolved', 'closed'];

    protected $fillable = [
        'company_id', 'user_id', 'subject', 'description', 'priority', 'status',
        'assigned_admin_id', 'last_reply_at', 'resolved_at', 'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'last_reply_at' => 'datetime',
            'resolved_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::created(function (Ticket $ticket) {
            // Based on the row's own DB-assigned id (unique and race-free
            // under concurrent creation) rather than a separately tracked
            // counter — this system is platform-wide, not per-company, so
            // there's no single company row to hold a next-number counter.
            $ticket->ticket_number = 'TKT-'.str_pad((string) $ticket->id, 6, '0', STR_PAD_LEFT);
            $ticket->saveQuietly();
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assignedAdmin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_admin_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(TicketReply::class);
    }

    /**
     * Replies a company user is allowed to see. Every customer-facing view
     * MUST go through this (not replies()) — internal notes are never
     * customer-visible.
     */
    public function publicReplies(): HasMany
    {
        return $this->replies()->where('is_internal_note', false);
    }

    public function internalNotes(): HasMany
    {
        return $this->replies()->where('is_internal_note', true);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(TicketAttachment::class);
    }

    public function priorityLabel(): string
    {
        return match ($this->priority) {
            'low' => __('Low'),
            'normal' => __('Normal'),
            'high' => __('High'),
            'urgent' => __('Urgent'),
            default => ucfirst($this->priority),
        };
    }

    public function priorityBadgeClasses(): string
    {
        return match ($this->priority) {
            'low' => 'bg-slate-100 text-slate-500',
            'normal' => 'bg-sky-50 text-sky-700',
            'high' => 'bg-amber-50 text-amber-700',
            'urgent' => 'bg-red-50 text-red-700',
            default => 'bg-slate-100 text-slate-500',
        };
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'open' => __('Open'),
            'in_progress' => __('In progress'),
            'waiting_customer' => __('Waiting for customer'),
            'resolved' => __('Resolved'),
            'closed' => __('Closed'),
            default => ucfirst(str_replace('_', ' ', $this->status)),
        };
    }

    public function statusBadgeClasses(): string
    {
        return match ($this->status) {
            'open' => 'bg-sky-50 text-sky-700',
            'in_progress' => 'bg-amber-50 text-amber-700',
            'waiting_customer' => 'bg-violet-50 text-violet-700',
            'resolved' => 'bg-emerald-50 text-emerald-700',
            'closed' => 'bg-slate-100 text-slate-500',
            default => 'bg-slate-100 text-slate-500',
        };
    }

    public function isOpenForReply(): bool
    {
        return ! in_array($this->status, ['closed'], true);
    }
}
