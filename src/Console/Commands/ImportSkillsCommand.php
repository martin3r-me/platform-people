<?php

namespace Platform\People\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Platform\People\Models\Employee;
use Platform\People\Models\EmployeeSkill;
use Platform\People\Models\Skill;

/**
 * Überträgt den Skill-Bestand aus Organization nach People — idempotent und
 * wiederholbar. Bewusst als Command statt (nur) Migration: robust gegen
 * blockierte Migrations-Batches und beliebig oft ausführbar.
 *
 *   1. Katalog:  organization_skills + organization_soft_skills -> people_skills
 *                (Soft -> category='social'), dedupliziert per (team_id, name)
 *   2. Employee: pro skill-tragende Person-Entity ein people_employees
 *                (find-or-create per org_entity_id, display_name = Entity-Name)
 *   3. Bestand:  organization_person_skills / _soft_skills -> people_employee_skills
 *                (Level + certified_at erhalten; Soft-Skills ohne certified_at)
 *
 * Quell-Tabellen werden ROH gelesen (kein Organization-Model-Import); Ziel wird
 * über Eloquent geschrieben, damit uuid-Boot + Graph-Spiegelung greifen.
 */
class ImportSkillsCommand extends Command
{
    protected $signature = 'people:import-skills
        {--team= : Nur dieses Team (org-skill/entity team_id) übertragen}
        {--dry-run : Nur anzeigen, was übertragen würde — nichts schreiben}';

    protected $description = 'Überträgt den Skill-Bestand aus Organization nach People (idempotent).';

    public function handle(): int
    {
        if (! Schema::hasTable('organization_skills') && ! Schema::hasTable('organization_soft_skills')) {
            $this->warn('Keine Organization-Skill-Tabellen gefunden — nichts zu übertragen.');
            return self::SUCCESS;
        }

        $dry = (bool) $this->option('dry-run');
        $team = $this->option('team') !== null ? (int) $this->option('team') : null;

        if ($dry) {
            $this->info('DRY-RUN — es wird nichts geschrieben.');
        }

        $newSkills = 0;
        $newEmployees = 0;
        $newAssignments = 0;
        $srcSkills = 0;
        $srcAssignments = 0;

        $skillMap = [];        // org_skills.id      => people_skills.id|null(dry-neu)
        $softMap = [];         // org_soft_skills.id => people_skills.id|null(dry-neu)

        // ── 1. Katalog ────────────────────────────────────────────────────
        if (Schema::hasTable('organization_skills')) {
            $q = DB::table('organization_skills')->whereNull('deleted_at');
            if ($team !== null) { $q->where('team_id', $team); }
            foreach ($q->get() as $s) {
                $srcSkills++;
                [$id, $created] = $this->resolveSkill($s->team_id, $s->name, $s->category ?: 'technical', $s->description, (bool) $s->is_active, $dry);
                $skillMap[$s->id] = $id;
                if ($created) { $newSkills++; }
            }
        }

        if (Schema::hasTable('organization_soft_skills')) {
            $q = DB::table('organization_soft_skills')->whereNull('deleted_at');
            if ($team !== null) { $q->where('team_id', $team); }
            foreach ($q->get() as $ss) {
                $srcSkills++;
                [$id, $created] = $this->resolveSkill($ss->team_id, $ss->name, 'social', $ss->description, (bool) $ss->is_active, $dry);
                $softMap[$ss->id] = $id;
                if ($created) { $newSkills++; }
            }
        }

        // ── 2. Employees (auto-create pro skill-tragende Person-Entity) ────
        $entityIds = collect();
        if (Schema::hasTable('organization_person_skills')) {
            $entityIds = $entityIds->merge(DB::table('organization_person_skills')->distinct()->pluck('person_entity_id'));
        }
        if (Schema::hasTable('organization_person_soft_skills')) {
            $entityIds = $entityIds->merge(DB::table('organization_person_soft_skills')->distinct()->pluck('person_entity_id'));
        }
        $entityIds = $entityIds->unique()->filter()->values();

        $employeeMap = [];     // org_entity_id => people_employees.id|null(dry-neu)
        $employeeTeam = [];    // org_entity_id => team_id
        foreach ($entityIds as $eid) {
            $entity = Schema::hasTable('organization_entities')
                ? DB::table('organization_entities')->where('id', $eid)->first()
                : null;
            if (! $entity) { continue; }
            if ($team !== null && (int) $entity->team_id !== $team) { continue; }

            $employeeTeam[$eid] = (int) $entity->team_id;
            [$empId, $created] = $this->resolveEmployee((int) $entity->team_id, (int) $eid, $entity->name ?? ('Entity #' . $eid), $dry);
            $employeeMap[$eid] = $empId;
            if ($created) { $newEmployees++; }
        }

        // ── 3. Bestand ────────────────────────────────────────────────────
        if (Schema::hasTable('organization_person_skills')) {
            foreach (DB::table('organization_person_skills')->get() as $ps) {
                if (! array_key_exists($ps->person_entity_id, $employeeMap) || ! array_key_exists($ps->skill_id, $skillMap)) {
                    continue;
                }
                $srcAssignments++;
                if ($this->resolveAssignment($employeeMap[$ps->person_entity_id], $skillMap[$ps->skill_id], $employeeTeam[$ps->person_entity_id] ?? null, $ps->level ?: 'basic', $ps->certified_at, $ps->notes, $dry)) {
                    $newAssignments++;
                }
            }
        }

        if (Schema::hasTable('organization_person_soft_skills')) {
            foreach (DB::table('organization_person_soft_skills')->get() as $pss) {
                if (! array_key_exists($pss->person_entity_id, $employeeMap) || ! array_key_exists($pss->soft_skill_id, $softMap)) {
                    continue;
                }
                $srcAssignments++;
                if ($this->resolveAssignment($employeeMap[$pss->person_entity_id], $softMap[$pss->soft_skill_id], $employeeTeam[$pss->person_entity_id] ?? null, $pss->level ?: 'basic', null, $pss->notes, $dry)) {
                    $newAssignments++;
                }
            }
        }

        // ── Report ────────────────────────────────────────────────────────
        $verb = $dry ? 'würde neu' : 'neu';
        $this->newLine();
        $this->table(
            ['', 'Quelle', $verb],
            [
                ['Skills (Katalog)', $srcSkills, $newSkills],
                ['Employees',        $entityIds->count(), $newEmployees],
                ['Zuordnungen',      $srcAssignments, $newAssignments],
            ]
        );

        if ($dry) {
            $this->comment('DRY-RUN abgeschlossen — ohne --dry-run erneut ausführen, um zu übertragen.');
        } else {
            $this->info('Übertragung abgeschlossen.');
        }

        return self::SUCCESS;
    }

    /** @return array{0:int|null,1:bool} [peopleSkillId|null, wurdeNeu] */
    private function resolveSkill($teamId, string $name, string $category, $description, bool $isActive, bool $dry): array
    {
        $existing = Skill::where('team_id', $teamId)->where('name', $name)->first();
        if ($existing) {
            return [$existing->id, false];
        }
        if ($dry) {
            return [null, true];
        }
        $skill = Skill::create([
            'team_id'     => $teamId,
            'name'        => $name,
            'category'    => $category,
            'description' => $description,
            'is_active'   => $isActive,
        ]);
        return [$skill->id, true];
    }

    /** @return array{0:int|null,1:bool} [employeeId|null, wurdeNeu] */
    private function resolveEmployee(int $teamId, int $orgEntityId, string $displayName, bool $dry): array
    {
        $existing = Employee::where('org_entity_id', $orgEntityId)->first();
        if ($existing) {
            return [$existing->id, false];
        }
        if ($dry) {
            return [null, true];
        }
        $employee = Employee::create([
            'team_id'       => $teamId,
            'org_entity_id' => $orgEntityId,
            'display_name'  => $displayName,
            'status'        => 'active',
        ]);
        return [$employee->id, true];
    }

    /** @return bool wurdeNeu */
    private function resolveAssignment(?int $employeeId, ?int $skillId, ?int $teamId, string $level, $certifiedAt, $notes, bool $dry): bool
    {
        if ($dry) {
            // Neu, wenn Employee/Skill erst angelegt würden (null) oder noch keine Zuordnung existiert.
            if ($employeeId === null || $skillId === null) {
                return true;
            }
            return ! EmployeeSkill::where('employee_id', $employeeId)->where('skill_id', $skillId)->exists();
        }

        $es = EmployeeSkill::firstOrCreate(
            ['employee_id' => $employeeId, 'skill_id' => $skillId],
            ['team_id' => $teamId, 'level' => $level, 'certified_at' => $certifiedAt ?: null, 'notes' => $notes],
        );
        return $es->wasRecentlyCreated;
    }
}
