<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * WIRKUNG sichtbar machen:
 *  - people_agent_rules: wie viel Wirkung eine Regel getragen hat (vom Engram gespiegelt, Credit Assignment)
 *  - people_impact_snapshots: der Wirkungs-Score je Mitarbeiter und Tag (Verlauf; täglich people:impact-snapshot)
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('people_agent_rules') && ! Schema::hasColumn('people_agent_rules', 'impact_credited')) {
            Schema::table('people_agent_rules', function (Blueprint $t) {
                $t->decimal('impact_credited', 10, 3)->nullable()->after('refuted');
                $t->unsignedInteger('impact_rounds')->default(0)->after('impact_credited');
            });
        }

        if (! Schema::hasTable('people_impact_snapshots')) {
            Schema::create('people_impact_snapshots', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('team_id');
                $t->unsignedBigInteger('employee_id');
                $t->date('taken_on');
                $t->decimal('score', 10, 1);
                $t->json('parts')->nullable();
                $t->json('indicators')->nullable();
                $t->timestamps();
                $t->unique(['employee_id', 'taken_on'], 'people_impact_snap_emp_day');
                $t->index(['team_id', 'taken_on'], 'people_impact_snap_team_day');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('people_impact_snapshots');
        if (Schema::hasColumn('people_agent_rules', 'impact_credited')) {
            Schema::table('people_agent_rules', function (Blueprint $t) {
                $t->dropColumn(['impact_credited', 'impact_rounds']);
            });
        }
    }
};
