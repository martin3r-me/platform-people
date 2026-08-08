<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Macht aus Employment einen elementaren Arbeitsvertrag — bewusst schlank:
 * Laufzeit/Befristung/Probezeit, Arbeitszeit, Urlaubsanspruch, EINE Bruttozahl.
 * KEIN Payroll: kein Tarif, keine Steuer, keine Sozialversicherung.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('people_employments', function (Blueprint $table) {
            // Befristung / Probezeit
            $table->boolean('is_fixed_term')->default(false)->after('status');
            $table->date('fixed_term_end_date')->nullable()->after('is_fixed_term');
            $table->date('probation_end_date')->nullable()->after('fixed_term_end_date');

            // Arbeitszeit-Detail (Wochenstunden/FTE existieren bereits)
            $table->decimal('weekly_days', 3, 1)->nullable()->after('weekly_hours'); // Arbeitstage/Woche

            // Urlaubsanspruch pro Jahr
            $table->unsignedSmallInteger('annual_vacation_days')->nullable()->after('probation_end_date');

            // Vergütung — schlank: Typ + EIN Bruttobetrag (Monatsgehalt ODER Stundenlohn)
            $table->string('wage_type')->nullable()->after('annual_vacation_days');   // salary | hourly
            $table->decimal('gross_amount', 10, 2)->nullable()->after('wage_type');

            // Arbeitsort (Stadt) — optional
            $table->string('work_location')->nullable()->after('gross_amount');
        });
    }

    public function down(): void
    {
        Schema::table('people_employments', function (Blueprint $table) {
            $table->dropColumn([
                'is_fixed_term',
                'fixed_term_end_date',
                'probation_end_date',
                'weekly_days',
                'annual_vacation_days',
                'wage_type',
                'gross_amount',
                'work_location',
            ]);
        });
    }
};
