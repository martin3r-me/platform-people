<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('people_job_profiles', function (Blueprint $table) {
            // VSM-Funktions-Stufe(n) der Rolle als Liste (z.B. ["S1"], ["S4"], ["S2","S3","S3star"]).
            // Eine Rolle kann MEHRERE S tragen (Steward = S2+S3+S3*); sie spalten sich beim Wachsen.
            // Treibt rollen-gescopte Sinne im Daemon (z.B. Pulse nur für S2↑, nicht für S1-Doer).
            $table->json('vsm_level')->nullable()->after('level');
        });
    }

    public function down(): void
    {
        Schema::table('people_job_profiles', function (Blueprint $table) {
            $table->dropColumn('vsm_level');
        });
    }
};
