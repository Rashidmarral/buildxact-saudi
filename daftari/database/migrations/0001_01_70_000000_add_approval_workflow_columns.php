<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            // Null/0 = approval workflow disabled for that document type —
            // every existing company keeps today's behavior (instant
            // approve/post) until an owner opts in from Settings.
            $table->decimal('po_approval_threshold', 12, 2)->nullable()->after('negative_number_format');
            $table->decimal('expense_approval_threshold', 12, 2)->nullable()->after('po_approval_threshold');
        });

        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->foreignId('approved_by')->nullable()->after('status')->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable()->after('approved_by');
            $table->text('rejection_reason')->nullable()->after('approved_at');
        });

        Schema::table('expenses', function (Blueprint $table) {
            // Every pre-existing expense was created under the old
            // "always instantly posted" behavior, so backfilling
            // 'approved' here (rather than leaving it null) keeps them
            // showing as already-settled instead of newly pending.
            $table->string('status', 20)->default('approved')->after('tax_category');
            $table->foreignId('approved_by')->nullable()->after('status')->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable()->after('approved_by');
            $table->text('rejection_reason')->nullable()->after('approved_at');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn(['po_approval_threshold', 'expense_approval_threshold']);
        });

        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('approved_by');
            $table->dropColumn(['approved_at', 'rejection_reason']);
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->dropConstrainedForeignId('approved_by');
            $table->dropColumn(['status', 'approved_at', 'rejection_reason']);
        });
    }
};
