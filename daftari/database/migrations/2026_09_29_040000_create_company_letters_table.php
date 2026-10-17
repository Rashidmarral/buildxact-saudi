<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The flexible bilingual letter/agreement generator (machinery sale,
 * purchase, and rental agreements; work handover letters; anything else a
 * company wants to draft). document_type is a free string, not a DB enum
 * — a brand new kind of letter never needs a migration, only a new entry
 * in App\Support\LetterPresets for its starter content. `content` is an
 * ordered array of {text_en, text_ar, is_heading} blocks the user can
 * freely add/edit/remove — the fixed header fields (date, reference, who
 * it's to, subject) and the two-party signature block are rendered from
 * this table's own columns, not stored as blocks.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_letters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('machinery_asset_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('machinery_rental_contract_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->string('document_type', 40);
            $table->string('reference_number', 40);
            $table->string('title');
            $table->date('letter_date');
            $table->string('party_a_role', 60)->default('Company');
            $table->string('party_a_name')->nullable();
            $table->string('party_b_role', 60)->default('Client');
            $table->string('party_b_name');
            $table->text('party_b_details')->nullable();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->string('language_mode', 20)->default('bilingual'); // bilingual, english_only, arabic_only
            $table->json('content');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'reference_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_letters');
    }
};
