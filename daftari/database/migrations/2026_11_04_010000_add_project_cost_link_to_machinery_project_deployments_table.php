<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Hamly's Equipment tab on a project actually posts machinery usage as
 * a real cost against that project ... make equipment usage post real
 * project cost" (user-confirmed). Once a deployment ends (its final
 * cost — rate x days — is actually known), MachineryProjectDeployment::
 * postCost() creates a real ProjectCost ledger row (cost_head
 * 'equipment') linked here, so it now counts in Project::costs(). A
 * still-active (ongoing) deployment posts nothing yet — its cost isn't
 * final until it ends.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('machinery_project_deployments', function (Blueprint $table) {
            $table->foreignId('project_cost_id')->nullable()->after('internal_daily_rate')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('machinery_project_deployments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('project_cost_id');
        });
    }
};
