<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Employment — das Beschaeftigungsverhaeltnis, schlank.
 *
 * Ein Employee kann mehrere (historische) Employments haben. Kein Payroll,
 * kein Tarif — nur Art, Soll-Arbeitszeit und Laufzeit.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('people_employments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();

            $table->foreignId('employee_id')
                  ->constrained('people_employees')
                  ->cascadeOnDelete();

            $table->string('employment_type')->default('regular'); // regular/part_time/temporary/marginal/freelance
            $table->decimal('fte', 4, 2)->nullable();              // Vollzeitaequivalent 0.00–1.00
            $table->decimal('weekly_hours', 5, 2)->nullable();     // Soll-Wochenstunden
            $table->date('started_on')->nullable();
            $table->date('ended_on')->nullable();
            $table->string('status')->default('active');           // active/ended
            $table->text('note')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['employee_id']);
            $table->index(['team_id', 'status']);
            $table->index(['uuid']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('people_employments');
    }
};
