<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Override-Rollen pro Employee-JobProfile-Zuweisung (individuelle Anteile).
 * Wenn leer, gelten die JobProfile-Defaults. role_id ist eine WEICHE Referenz
 * auf organization_roles (kein FK — Rollen bleiben in Organization).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('people_employee_job_profile_roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_job_profile_id')
                ->constrained('people_employee_job_profiles')
                ->cascadeOnDelete();

            // Weiche Referenz auf organization_roles.id (kein FK).
            $table->unsignedBigInteger('role_id');

            $table->unsignedTinyInteger('percentage_share')->default(0);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['employee_job_profile_id', 'role_id'], 'ppl_ejpr_unique');
            $table->index(['employee_job_profile_id', 'sort_order'], 'ppl_ejpr_order_idx');
            $table->index(['role_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('people_employee_job_profile_roles');
    }
};
