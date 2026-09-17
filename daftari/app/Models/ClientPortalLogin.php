<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * A one-time magic-link login for the client self-service portal —
 * Client records aren't Authenticatable and don't have passwords, so this
 * plays the same role a password-reset token does: prove control of the
 * client's email, then establish a session, nothing more.
 */
class ClientPortalLogin extends Model
{
    public $timestamps = false;

    protected $fillable = ['client_id', 'token_hash', 'expires_at', 'used_at'];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * Creates a fresh login token for a client and returns the RAW token —
     * only this return value ever gets emailed; the hash is all that's
     * persisted, same pattern as Laravel's own password_reset_tokens.
     */
    public static function issueFor(Client $client): string
    {
        $token = Str::random(64);

        self::create([
            'client_id' => $client->id,
            'token_hash' => hash('sha256', $token),
            'expires_at' => now()->addMinutes(15),
            'created_at' => now(),
        ]);

        return $token;
    }

    public static function consume(string $token): ?Client
    {
        $login = self::where('token_hash', hash('sha256', $token))
            ->whereNull('used_at')
            ->where('expires_at', '>=', now())
            ->first();

        if (! $login) {
            return null;
        }

        $login->update(['used_at' => now()]);

        return $login->client;
    }
}
