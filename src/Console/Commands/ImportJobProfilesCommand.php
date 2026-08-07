<?php

namespace Platform\People\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Platform\People\Models\Employee;
use Symfony\Component\Uid\UuidV7;

/**
 * Überträgt JobProfiles + Zuweisungen aus Organization nach People (Phase 2b),
 * idempotent und wiederholbar. Setzt voraus, dass der Skill-Katalog bereits
 * übertragen ist (people:import-skills), da JobProfile-Skills per Name auf
 * people_skills gemappt werden.
 *
 *   organization_job_profiles              -> people_job_profiles (uuid erhalten)
 *   organization_job_profile_roles         -> people_job_profile_roles (role_id = Org-ID, weich)
 *   organization_job_profile_(soft_)skills -> people_job_profile_skills (skill_id via Name -> people_skills)
 *   organization_person_job_profiles       -> people_employee_job_profiles (person -> employee)
 *   organization_person_job_profile_roles  -> people_employee_job_profile_roles
 */
class ImportJobProfilesCommand extends Command
{
    protected $signature = 'people:import-job-profiles
        {--team= : Nur dieses Team übertragen}
        {--dry-run : Nur anzeigen, was übertragen würde}';

    protected $description = 'Überträgt JobProfiles + Zuweisungen aus Organization nach People (idempotent).';

    private array $skillIdCache = [];   // "table:orgSkillId:team" => people_skills.id|null

    public function handle(): int
    {
        if (! Schema::hasTable('organization_job_profiles')) {
            $this->warn('Keine organization_job_profiles-Tabelle — nichts zu übertragen.');
            return self::SUCCESS;
        }

        $dry = (bool) $this->option('dry-run');
        $team = $this->option('team') !== null ? (int) $this->option('team') : null;
        $now = Carbon::now();

        if ($dry) {
            $this->info('DRY-RUN — es wird nichts geschrieben.');
        }

        $jpMap = [];        // org job_profile.id => people_job_profiles.id
        $newJp = 0; $srcJp = 0;
        $newAssign = 0; $srcAssign = 0;

        // ── 1. JobProfiles ────────────────────────────────────────────────
        $q = DB::table('organization_job_profiles')->whereNull('deleted_at');
        if ($team !== null) { $q->where('team_id', $team); }
        foreach ($q->get() as $jp) {
            $srcJp++;
            $existing = DB::table('people_job_profiles')->where('uuid', $jp->uuid)->first();
            if ($existing) {
                $jpMap[$jp->id] = $existing->id;
                continue;
            }
            $newJp++;
            if ($dry) { $jpMap[$jp->id] = null; continue; }

            $jpMap[$jp->id] = DB::table('people_job_profiles')->insertGetId([
                'uuid'               => $jp->uuid,
                'team_id'            => $jp->team_id,
                'user_id'            => $jp->user_id,
                'name'               => $jp->name,
                'description'        => $jp->description,
                'purpose'            => $jp->purpose,
                'job_family'         => $jp->job_family,
                'content'            => $jp->content,
                'level'              => $jp->level,
                'skills'             => $jp->skills,
                'responsibilities'   => $jp->responsibilities,
                'requirements'       => $jp->requirements,
                'soft_skills'        => $jp->soft_skills,
                'kpis'               => $jp->kpis,
                'exclusion_criteria' => $jp->exclusion_criteria,
                'work_model'         => $jp->work_model,
                'reporting'          => $jp->reporting,
                'status'             => $jp->status,
                'owner_entity_id'    => $jp->owner_entity_id,
                'effective_from'     => $jp->effective_from,
                'effective_to'       => $jp->effective_to,
                'created_at'         => $jp->created_at ?? $now,
                'updated_at'         => $jp->updated_at ?? $now,
            ]);
        }

        if (! $dry) {
            // ── 2. JobProfile-Rollen (role_id = Org-ID, weiche Ref) ────────
            foreach (DB::table('organization_job_profile_roles')->get() as $r) {
                $pjpId = $jpMap[$r->job_profile_id] ?? null;
                if (! $pjpId) { continue; }
                $this->upsert('people_job_profile_roles', ['job_profile_id' => $pjpId, 'role_id' => $r->role_id], [
                    'percentage_share' => $r->percentage_share,
                    'sort_order'       => $r->sort_order,
                    'created_at'       => $now, 'updated_at' => $now,
                ]);
            }

            // ── 3. JobProfile-Skills (beide Quell-Pivots -> ein Ziel) ──────
            foreach ([['organization_job_profile_skills', 'skill_id', 'organization_skills'],
                      ['organization_job_profile_soft_skills', 'soft_skill_id', 'organization_soft_skills']] as [$pivot, $fk, $catalog]) {
                if (! Schema::hasTable($pivot)) { continue; }
                foreach (DB::table($pivot)->get() as $ps) {
                    $pjpId = $jpMap[$ps->job_profile_id] ?? null;
                    if (! $pjpId) { continue; }
                    $peopleSkillId = $this->peopleSkillId($catalog, $ps->$fk);
                    if (! $peopleSkillId) { continue; }
                    $this->upsert('people_job_profile_skills', ['job_profile_id' => $pjpId, 'skill_id' => $peopleSkillId], [
                        'level'       => $ps->level ?? 'expert',
                        'is_required' => $ps->is_required ?? true,
                        'sort_order'  => $ps->sort_order ?? 0,
                    ]);
                }
            }
        }

        // ── 4. Person-JobProfile-Zuweisungen -> Employee ──────────────────
        $ejpMap = [];   // org person_job_profile.id => people_employee_job_profiles.id
        foreach (DB::table('organization_person_job_profiles')->whereNull('deleted_at')->get() as $pjp) {
            $entity = Schema::hasTable('organization_entities')
                ? DB::table('organization_entities')->where('id', $pjp->person_entity_id)->first() : null;
            if (! $entity) { continue; }
            if ($team !== null && (int) $entity->team_id !== $team) { continue; }
            if (! array_key_exists($pjp->job_profile_id, $jpMap)) { continue; }

            $srcAssign++;
            $peopleJpId = $jpMap[$pjp->job_profile_id];

            if ($dry) {
                $emp = Employee::where('org_entity_id', $pjp->person_entity_id)->first();
                $isNew = ! ($emp && $peopleJpId && DB::table('people_employee_job_profiles')
                    ->where('employee_id', $emp->id)->where('job_profile_id', $peopleJpId)
                    ->where('context_entity_id', $pjp->context_entity_id)->exists());
                if ($isNew) { $newAssign++; }
                continue;
            }

            $employee = Employee::firstOrCreate(
                ['org_entity_id' => $pjp->person_entity_id],
                ['team_id' => $entity->team_id, 'display_name' => $entity->name ?? ('Entity #' . $pjp->person_entity_id), 'status' => 'active'],
            );

            $existing = DB::table('people_employee_job_profiles')
                ->where('employee_id', $employee->id)->where('job_profile_id', $peopleJpId)
                ->where('context_entity_id', $pjp->context_entity_id)->first();
            if ($existing) { $ejpMap[$pjp->id] = $existing->id; continue; }

            $newAssign++;
            $ejpMap[$pjp->id] = DB::table('people_employee_job_profiles')->insertGetId([
                'uuid'              => (string) UuidV7::generate(),
                'team_id'           => $employee->team_id,
                'employee_id'       => $employee->id,
                'job_profile_id'    => $peopleJpId,
                'context_entity_id' => $pjp->context_entity_id,
                'percentage'        => $pjp->percentage ?? 100,
                'is_primary'        => $pjp->is_primary ?? false,
                'valid_from'        => $pjp->valid_from,
                'valid_to'          => $pjp->valid_to,
                'note'              => $pjp->note,
                'created_at'        => $pjp->created_at ?? $now,
                'updated_at'        => $pjp->updated_at ?? $now,
            ]);
        }

        // ── 5. Override-Rollen der Zuweisungen ─────────────────────────────
        if (! $dry && Schema::hasTable('organization_person_job_profile_roles')) {
            foreach (DB::table('organization_person_job_profile_roles')->get() as $pr) {
                $ejpId = $ejpMap[$pr->person_job_profile_id] ?? null;
                if (! $ejpId) { continue; }
                $this->upsert('people_employee_job_profile_roles', ['employee_job_profile_id' => $ejpId, 'role_id' => $pr->role_id], [
                    'percentage_share' => $pr->percentage_share,
                    'sort_order'       => $pr->sort_order,
                    'created_at'       => $now, 'updated_at' => $now,
                ]);
            }
        }

        $verb = $dry ? 'würde neu' : 'neu';
        $this->newLine();
        $this->table(['', 'Quelle', $verb], [
            ['JobProfiles',       $srcJp, $newJp],
            ['Employee-Zuweisung', $srcAssign, $newAssign],
        ]);
        $this->line($dry ? 'DRY-RUN abgeschlossen.' : 'Übertragung abgeschlossen (inkl. Rollen- & Skill-Verknüpfungen).');

        return self::SUCCESS;
    }

    /** Mappt eine Org-Katalog-Skill-ID auf die people_skills-ID (per Name, team-scoped). */
    private function peopleSkillId(string $catalog, $orgSkillId): ?int
    {
        $key = $catalog . ':' . $orgSkillId;
        if (array_key_exists($key, $this->skillIdCache)) {
            return $this->skillIdCache[$key];
        }
        $src = DB::table($catalog)->where('id', $orgSkillId)->first();
        $id = $src
            ? DB::table('people_skills')->where('team_id', $src->team_id)->where('name', $src->name)->value('id')
            : null;
        return $this->skillIdCache[$key] = ($id ? (int) $id : null);
    }

    /** Idempotenter Insert: legt nur an, wenn die Schlüssel-Kombination fehlt. */
    private function upsert(string $table, array $keys, array $values): void
    {
        if (DB::table($table)->where($keys)->exists()) {
            return;
        }
        DB::table($table)->insert($keys + $values);
    }
}
