<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Employee — Stammsatz des angestellten Menschen (bewusst schlank).
 *
 * Join-Anker: `user_id` (Platform-User) und `org_entity_id` (Organization-
 * Person-Entity). Letzteres ist eine WEICHE Referenz ohne DB-FK — People darf
 * Organization referenzieren, aber nicht hart an dessen Schema koppeln.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('people_employees', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            // Weiche Referenz auf Organization-Person-Entity (kein FK: Entkopplung).
            $table->unsignedBigInteger('org_entity_id')->nullable();

            $table->string('display_name');
            $table->string('employee_number')->nullable();
            $table->string('status')->default('active');   // active/inactive/left

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['team_id', 'employee_number']);
            $table->index(['team_id', 'status']);
            $table->index(['org_entity_id']);
            $table->index(['uuid']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('people_employees');
    }
};
