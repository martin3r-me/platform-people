<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * JobProfile ↔ Skill (benötigte Fähigkeiten). Vereint die früheren zwei
 * Organization-Pivots (job_profile_skills + _soft_skills) zu EINEM — der
 * People-Skill-Katalog unterscheidet Hard/Soft bereits über category.
 * skill_id ist ein echter FK auf people_skills (intra-modul).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('people_job_profile_skills', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_profile_id')
                ->constrained('people_job_profiles')
                ->cascadeOnDelete();
            $table->foreignId('skill_id')
                ->constrained('people_skills')
                ->cascadeOnDelete();

            $table->string('level')->default('expert');   // basic/advanced/expert
            $table->boolean('is_required')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);

            $table->unique(['job_profile_id', 'skill_id'], 'ppl_jp_skill_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('people_job_profile_skills');
    }
};
