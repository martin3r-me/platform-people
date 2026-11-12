<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('people_employees', function (Blueprint $table) {
            // Art des Mitglieds: human | agent. Agenten (Engrams) sind Mitarbeiter wie Menschen —
            // Stelle, Skills und Entwicklung laufen über dieselben Tabellen, ohne Sonderweg.
            $table->string('kind', 16)->default('human')->after('display_name');
            $table->index(['team_id', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::table('people_employees', function (Blueprint $table) {
            $table->dropIndex(['team_id', 'kind']);
            $table->dropColumn('kind');
        });
    }
};
