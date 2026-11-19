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

    /**
     * Security audit finding SEC-09: the previous read-then-update let two
     * concurrent requests with the same valid token both pass the
     * whereNull('used_at') check before either write landed, consuming it
     * twice. The UPDATE itself is the atomic check-and-set here — its WHERE
     * clause (including used_at IS NULL) only matches, and only updates,
     * for one of two concurrent identical requests; a losing request's
     * UPDATE simply affects zero rows.
     */
    public static function consume(string $token): ?Client
    {
        $hash = hash('sha256', $token);

        $updated = self::where('token_hash', $hash)
            ->whereNull('used_at')
            ->where('expires_at', '>=', now())
            ->update(['used_at' => now()]);

        if ($updated !== 1) {
            return null;
        }

        return self::where('token_hash', $hash)->first()?->client;
    }
}
