<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Platform\People\Models\Employee;
use Platform\People\Support\OrganizationLink;

/**
 * Backfill: für bereits vorhandene Mitarbeiter mit `org_entity_id` den graph-nativen
 * dimension_link (Alias people_employee) nachziehen. Neu/geänderte Mitarbeiter laufen
 * über den saved-Hook des Models; diese Migration holt den Bestand (z.B. aus der
 * organization→people-ETL) einmalig nach. Idempotent + fehlertolerant.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('people_employees')) {
            return;
        }

        Employee::query()
            ->whereNotNull('org_entity_id')
            ->chunkById(200, function ($employees) {
                foreach ($employees as $employee) {
                    OrganizationLink::sync(
                        'people_employee',
                        (int) $employee->id,
                        (int) $employee->org_entity_id,
                        $employee->team_id ? (int) $employee->team_id : null,
                        null,
                    );
                }
            });
    }

    public function down(): void
    {
        // Kein Reverse — dimension_links sind additiv/idempotent.
    }
};
