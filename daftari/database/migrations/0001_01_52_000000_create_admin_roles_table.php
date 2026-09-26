<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Platform-level roles for admin_staff users — deliberately a separate
     * table from the company-scoped "roles" table (which holds per-company
     * permission sets like "accountant"/"sales"): admin roles are global,
     * not tied to any company_id, and use a distinct permission catalog
     * (see App\Support\AdminPermissions).
     */
    public function up(): void
    {
        Schema::create('admin_roles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('name_ar')->nullable();
            $table->string('slug')->unique();
            $table->boolean('is_system')->default(false);
            $table->json('permissions')->nullable();
            $table->timestamps();
        });

        Schema::create('admin_role_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_role_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['admin_role_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_role_user');
        Schema::dropIfExists('admin_roles');
    }
};
