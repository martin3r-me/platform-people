<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Wer eine Stelle trägt, steht in people_employee_job_profiles; zu welcher Einheit sie gehört,
        // weiß die Organisation (dimension_link people_job_profile → Einheits-Knoten). Die Spalte war beides
        // zugleich und fällt weg.
        if (Schema::hasColumn('people_job_profiles', 'owner_entity_id')) {
            Schema::table('people_job_profiles', function (Blueprint $table) {
                $table->dropIndex(['owner_entity_id']);
                $table->dropColumn('owner_entity_id');
            });
        }
    }

    public function down(): void
    {
        Schema::table('people_job_profiles', function (Blueprint $table) {
            $table->unsignedBigInteger('owner_entity_id')->nullable();
            $table->index(['owner_entity_id']);
        });
    }
};
