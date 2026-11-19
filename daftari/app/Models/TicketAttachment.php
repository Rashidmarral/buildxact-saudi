<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Stored on the PRIVATE 'local' disk (never 'public') and only ever served
 * through downloadResponse() below via an authorization-checked controller
 * route — see the migration's docblock for why this doesn't reuse the
 * generic polymorphic Attachment model.
 */
class TicketAttachment extends Model
{
    use BelongsToCompany;

    public const DISK = 'local';

    /**
     * Single source of truth for upload validation, used identically by
     * both the company-side and admin-side ticket controllers so the two
     * can never silently drift apart. Extensions are checked against the
     * file's real MIME signature (Laravel's `mimes` rule sniffs content,
     * not just the client-supplied extension), and the 10MB cap matches
     * the existing company-documents upload convention
     * (SettingsController::storeDocument).
     */
    public static function validationRules(): array
    {
        // No 'zip' — an archive's real contents aren't inspected by the
        // mimes: check, so it's the one extension here that could smuggle
        // a disguised executable past this whitelist.
        return ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,gif,doc,docx,xls,xlsx,txt', 'max:10240'];
    }

    protected $fillable = [
        'ticket_id', 'ticket_reply_id', 'company_id', 'uploaded_by',
        'original_name', 'path', 'size', 'mime_type', 'is_internal',
    ];

    protected function casts(): array
    {
        return [
            'is_internal' => 'boolean',
        ];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function reply(): BelongsTo
    {
        return $this->belongsTo(TicketReply::class, 'ticket_reply_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function humanSize(): string
    {
        $size = $this->size;

        if ($size < 1024) {
            return $size.' B';
        }

        if ($size < 1024 * 1024) {
            return round($size / 1024, 1).' KB';
        }

        return round($size / (1024 * 1024), 1).' MB';
    }

    /**
     * The only sanctioned way to create one of these rows — stores the
     * file on the private disk under a per-ticket folder and records it,
     * used identically by both the company-side and admin-side
     * controllers. $isInternal must be true when $reply is an internal
     * note (or null with no reply, only ever true for an admin-only
     * attachment) — callers pass it explicitly rather than this method
     * inferring it, so the internal flag is never accidentally wrong.
     */
    public static function storeUpload(Ticket $ticket, ?TicketReply $reply, UploadedFile $file, ?int $uploadedBy, bool $isInternal): self
    {
        $path = $file->store("ticket-attachments/{$ticket->id}", self::DISK);

        return self::create([
            'ticket_id' => $ticket->id,
            'ticket_reply_id' => $reply?->id,
            'company_id' => $ticket->company_id,
            'uploaded_by' => $uploadedBy,
            'original_name' => $file->getClientOriginalName(),
            'path' => $path,
            'size' => $file->getSize(),
            'mime_type' => $file->getMimeType(),
            'is_internal' => $isInternal,
        ]);
    }

    /**
     * The only sanctioned way to serve this file's bytes — streams it
     * straight from the private disk with the original filename restored
     * via Laravel's own (properly header-encoded) download response,
     * rather than a hand-built Content-Disposition string.
     */
    public function downloadResponse()
    {
        return Storage::disk(self::DISK)->download($this->path, $this->original_name);
    }
}
