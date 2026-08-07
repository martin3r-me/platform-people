<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Skill — Kompetenz-Katalog. Vereint Hard- und Soft-Skills ueber `category`
 * (technical/methodical/domain/social). Loest die vier Organization-Skill-
 * Tabellen ab: der Faehigkeits-Bestand gehoert zum Menschen, nicht zur Struktur.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('people_skills', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();

            $table->string('name');
            $table->string('category')->default('technical'); // technical/methodical/domain/social
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['team_id', 'name']);
            $table->index(['team_id', 'category', 'is_active']);
            $table->index(['uuid']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('people_skills');
    }
};
