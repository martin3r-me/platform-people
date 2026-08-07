<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * JobProfile ↔ Rolle (Default-Anteile). Die Rolle bleibt in Organization —
 * role_id ist eine WEICHE Referenz auf organization_roles (kein FK).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('people_job_profile_roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_profile_id')
                ->constrained('people_job_profiles')
                ->cascadeOnDelete();

            // Weiche Referenz auf organization_roles.id (kein FK — Entkopplung).
            $table->unsignedBigInteger('role_id');

            $table->unsignedTinyInteger('percentage_share')->default(0);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['job_profile_id', 'role_id'], 'ppl_jp_role_unique');
            $table->index(['job_profile_id', 'sort_order'], 'ppl_jp_role_order_idx');
            $table->index(['role_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('people_job_profile_roles');
    }
};
