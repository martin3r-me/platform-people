<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Arbeitgeber-Registry — ein HR-Overlay auf einen Org-Carrier-Knoten
 * (vsm_class='carrier', z. B. OFFLINE.AG / BHG.DIGITAL). Kein Nachbau der
 * Org-Struktur: org_entity_id ist eine WEICHE Referenz auf den Carrier.
 * Hält die HR-Defaults, die Verträge dieses Arbeitgebers vorbelegen.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('people_employers', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();

            // Weiche Referenz auf den Org-Carrier-Knoten (kein FK).
            $table->unsignedBigInteger('org_entity_id')->nullable();

            $table->string('name');                 // Cache/Anzeigename
            $table->boolean('is_active')->default(true);

            // HR-Defaults für Verträge dieses Arbeitgebers
            $table->unsignedSmallInteger('default_vacation_days')->nullable();
            $table->decimal('default_weekly_hours', 5, 2)->nullable();
            $table->string('working_time_model')->nullable();
            $table->text('note')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['team_id', 'is_active']);
            $table->index(['org_entity_id']);
            $table->index(['uuid']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('people_employers');
    }
};
