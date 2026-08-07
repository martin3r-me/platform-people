<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * EmployeeSkill — der Faehigkeits-Bestand: welcher Mensch kann welchen Skill,
 * auf welchem Level, seit wann zertifiziert. Genau das, was aus Organization
 * (OrganizationPersonSkill / OrganizationPersonSoftSkill) hierher wandert.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('people_employee_skills', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();

            $table->foreignId('employee_id')
                  ->constrained('people_employees')
                  ->cascadeOnDelete();

            $table->foreignId('skill_id')
                  ->constrained('people_skills')
                  ->cascadeOnDelete();

            $table->string('level')->default('basic'); // basic/advanced/expert
            $table->date('certified_at')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['employee_id', 'skill_id']);
            $table->index(['skill_id']);
            $table->index(['team_id']);
            $table->index(['uuid']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('people_employee_skills');
    }
};
