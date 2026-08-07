<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Uid\UuidV7;

/**
 * ETL (Phase 2a): Skill-Bestand aus Organization nach People wandern.
 *
 * Liest die organization_*-Tabellen ROH (kein Model-Import) — People koppelt
 * nicht an Organization. Guarded per Schema::hasTable, damit Installs ohne
 * Organization ein No-op sind. Idempotent (find-or-create ueberall):
 *
 *   1. Katalog:  organization_skills + organization_soft_skills -> people_skills
 *                (Soft -> category='social'), dedupliziert per (team_id, name)
 *   2. Employee: pro skill-tragende Person-Entity ein people_employees
 *                (find-or-create per org_entity_id, display_name = Entity-Name)
 *   3. Bestand:  organization_person_skills / _soft_skills -> people_employee_skills
 *                (Level + certified_at erhalten; Soft-Skills ohne certified_at)
 */
return new class extends Migration
{
    public function up(): void
    {
        // Ohne Quell-Tabellen nichts zu tun.
        if (! Schema::hasTable('organization_person_skills')
            && ! Schema::hasTable('organization_person_soft_skills')) {
            return;
        }

        $now = Carbon::now();

        $uuid = fn (): string => (string) UuidV7::generate();

        // ── 1. Katalog: Skills ────────────────────────────────────────────
        $skillIdMap = [];      // org_skills.id      => people_skills.id
        $softIdMap = [];       // org_soft_skills.id => people_skills.id

        if (Schema::hasTable('organization_skills')) {
            foreach (DB::table('organization_skills')->whereNull('deleted_at')->get() as $s) {
                $skillIdMap[$s->id] = $this->firstOrCreateSkill(
                    $s->team_id,
                    $s->name,
                    $s->category ?: 'technical',
                    $s->description,
                    (bool) $s->is_active,
                    $uuid,
                    $now,
                );
            }
        }

        if (Schema::hasTable('organization_soft_skills')) {
            foreach (DB::table('organization_soft_skills')->whereNull('deleted_at')->get() as $ss) {
                $softIdMap[$ss->id] = $this->firstOrCreateSkill(
                    $ss->team_id,
                    $ss->name,
                    'social',
                    $ss->description,
                    (bool) $ss->is_active,
                    $uuid,
                    $now,
                );
            }
        }

        // ── 2. Employees (auto-create pro skill-tragende Person-Entity) ───
        $entityIds = collect();
        if (Schema::hasTable('organization_person_skills')) {
            $entityIds = $entityIds->merge(DB::table('organization_person_skills')->distinct()->pluck('person_entity_id'));
        }
        if (Schema::hasTable('organization_person_soft_skills')) {
            $entityIds = $entityIds->merge(DB::table('organization_person_soft_skills')->distinct()->pluck('person_entity_id'));
        }
        $entityIds = $entityIds->unique()->filter()->values();

        $employeeMap = [];     // org_entity_id => people_employees.id
        foreach ($entityIds as $eid) {
            $entity = Schema::hasTable('organization_entities')
                ? DB::table('organization_entities')->where('id', $eid)->first()
                : null;
            if (! $entity) {
                continue; // verwaiste Zuordnung ohne Entity -> ueberspringen
            }
            $employeeMap[$eid] = $this->firstOrCreateEmployee(
                $entity->team_id,
                $eid,
                $entity->name ?? ('Entity #' . $eid),
                $uuid,
                $now,
            );
        }

        // ── 3. Bestand: Person-Skills ─────────────────────────────────────
        if (Schema::hasTable('organization_person_skills')) {
            foreach (DB::table('organization_person_skills')->get() as $ps) {
                $employeeId = $employeeMap[$ps->person_entity_id] ?? null;
                $skillId = $skillIdMap[$ps->skill_id] ?? null;
                if (! $employeeId || ! $skillId) {
                    continue;
                }
                $this->upsertEmployeeSkill($employeeId, $skillId, $ps->level ?: 'basic', $ps->certified_at, $ps->notes, $uuid, $now);
            }
        }

        // Soft-Skills (kein certified_at in der Quelle)
        if (Schema::hasTable('organization_person_soft_skills')) {
            foreach (DB::table('organization_person_soft_skills')->get() as $pss) {
                $employeeId = $employeeMap[$pss->person_entity_id] ?? null;
                $skillId = $softIdMap[$pss->soft_skill_id] ?? null;
                if (! $employeeId || ! $skillId) {
                    continue;
                }
                $this->upsertEmployeeSkill($employeeId, $skillId, $pss->level ?: 'basic', null, $pss->notes, $uuid, $now);
            }
        }
    }

    /**
     * Reine Daten-Wanderung — kein sauberes down(). Ein Rollback wuerde die
     * gewanderten Zeilen nicht zurueckschreiben; die Quell-Tabellen bleiben
     * (Phase 2a-Drops kommen separat). Bewusst No-op.
     */
    public function down(): void
    {
        // Bewusst leer: ETL ist nicht reversibel ohne Datenverlust-Risiko.
    }

    private function firstOrCreateSkill($teamId, string $name, string $category, $description, bool $isActive, \Closure $uuid, Carbon $now): int
    {
        $existing = DB::table('people_skills')
            ->where('team_id', $teamId)
            ->where('name', $name)
            ->first();
        if ($existing) {
            return $existing->id;
        }

        return DB::table('people_skills')->insertGetId([
            'uuid'        => $uuid(),
            'team_id'     => $teamId,
            'name'        => $name,
            'category'    => $category,
            'description' => $description,
            'is_active'   => $isActive,
            'sort_order'  => 0,
            'created_at'  => $now,
            'updated_at'  => $now,
        ]);
    }

    private function firstOrCreateEmployee($teamId, $orgEntityId, string $displayName, \Closure $uuid, Carbon $now): int
    {
        $existing = DB::table('people_employees')
            ->where('org_entity_id', $orgEntityId)
            ->first();
        if ($existing) {
            return $existing->id;
        }

        return DB::table('people_employees')->insertGetId([
            'uuid'          => $uuid(),
            'team_id'       => $teamId,
            'user_id'       => null,
            'org_entity_id' => $orgEntityId,
            'display_name'  => $displayName,
            'employee_number' => null,
            'status'        => 'active',
            'created_at'    => $now,
            'updated_at'    => $now,
        ]);
    }

    private function upsertEmployeeSkill(int $employeeId, int $skillId, string $level, $certifiedAt, $notes, \Closure $uuid, Carbon $now): void
    {
        $exists = DB::table('people_employee_skills')
            ->where('employee_id', $employeeId)
            ->where('skill_id', $skillId)
            ->exists();
        if ($exists) {
            return;
        }

        $teamId = DB::table('people_employees')->where('id', $employeeId)->value('team_id');

        DB::table('people_employee_skills')->insert([
            'uuid'         => $uuid(),
            'team_id'      => $teamId,
            'employee_id'  => $employeeId,
            'skill_id'     => $skillId,
            'level'        => $level,
            'certified_at' => $certifiedAt,
            'notes'        => $notes,
            'created_at'   => $now,
            'updated_at'   => $now,
        ]);
    }
};
