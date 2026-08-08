<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Verknüpft den Arbeitsvertrag mit einem Arbeitgeber und (optional) einer
 * abgeleitet-fixierten Abteilung. employer_id ist ein echter FK auf
 * people_employers; department_entity_id ist eine WEICHE Org-Referenz (Override
 * der sonst abgeleiteten Abteilung).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('people_employments', function (Blueprint $table) {
            $table->foreignId('employer_id')->nullable()->after('employee_id')
                ->constrained('people_employers')->nullOnDelete();

            $table->unsignedBigInteger('department_entity_id')->nullable()->after('employer_id');
        });
    }

    public function down(): void
    {
        Schema::table('people_employments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('employer_id');
            $table->dropColumn('department_entity_id');
        });
    }
};
