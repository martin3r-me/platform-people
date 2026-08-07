<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * JobProfile — wiederverwendbare Stellenbeschreibung. Aus Organization abgezogen
 * (Phase 2b). owner_entity_id ist eine WEICHE Referenz auf einen Org-Knoten
 * (kein FK — People koppelt nicht ans Organization-Schema).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('people_job_profiles', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('name');
            $table->text('description')->nullable();
            $table->text('purpose')->nullable();
            $table->string('job_family')->nullable();
            $table->longText('content')->nullable();
            $table->string('level')->nullable();               // junior/mid/senior/lead/principal

            $table->json('skills')->nullable();
            $table->json('responsibilities')->nullable();
            $table->json('requirements')->nullable();
            $table->json('soft_skills')->nullable();
            $table->json('kpis')->nullable();
            $table->json('exclusion_criteria')->nullable();
            $table->json('work_model')->nullable();
            $table->json('reporting')->nullable();

            $table->string('status')->default('active');       // active/archived/draft
            $table->unsignedBigInteger('owner_entity_id')->nullable(); // weiche Org-Ref
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['team_id', 'status']);
            $table->index(['owner_entity_id']);
            $table->index(['uuid']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('people_job_profiles');
    }
};
