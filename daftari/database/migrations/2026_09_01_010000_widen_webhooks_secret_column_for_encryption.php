<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Webhook::secret now carries an 'encrypted' cast (commercial audit
 * finding: it was stored — and permanently re-displayed — in plaintext).
 * Laravel's encrypted-string ciphertext (base64 + IV + MAC) runs well past
 * the original 64-character column, so this widens it to text before any
 * webhook is re-saved under the new cast.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('webhooks', function (Blueprint $table) {
            $table->text('secret')->change();
        });
    }

    public function down(): void
    {
        Schema::table('webhooks', function (Blueprint $table) {
            $table->string('secret', 64)->change();
        });
    }
};
