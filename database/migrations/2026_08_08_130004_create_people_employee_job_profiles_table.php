<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Zuweisung Employee ↔ JobProfile mit Auslastung. Ersetzt das org-seitige
 * PersonJobProfile (person_entity → employee). context_entity_id ist eine
 * WEICHE Org-Referenz (kein FK).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('people_employee_job_profiles', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();

            $table->foreignId('employee_id')
                  ->constrained('people_employees')
                  ->cascadeOnDelete();

            $table->foreignId('job_profile_id')
                  ->constrained('people_job_profiles')
                  ->cascadeOnDelete();

            $table->unsignedBigInteger('context_entity_id')->nullable(); // weiche Org-Ref

            $table->unsignedTinyInteger('percentage')->default(100);
            $table->boolean('is_primary')->default(false);
            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();
            $table->text('note')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['employee_id']);
            $table->index(['job_profile_id']);
            $table->index(['team_id']);
            $table->index(['uuid']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('people_employee_job_profiles');
    }
};
