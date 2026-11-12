<?php

namespace Platform\People\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardGetOperations;
use Platform\People\Models\JobProfile;
use Platform\People\Support\HeldJobProfile;
use Platform\People\Support\OrganizationLink;
use Platform\People\Tools\Concerns\ResolvesPeopleTeam;

class ListJobProfilesTool implements ToolContract, ToolMetadataContract
{
    use HasStandardGetOperations;
    use ResolvesPeopleTeam;

    public function getName(): string
    {
        return 'people.job_profiles.GET';
    }

    public function getDescription(): string
    {
        return 'GET /people/job-profiles - Listet JobProfile-Templates (Stellenbeschreibungen) im Root/Elterteam. Unterstützt filters/search/sort/limit/offset.';
    }

    public function getSchema(): array
    {
        return $this->mergeSchemas(
            $this->getStandardGetSchema(['team_id', 'status', 'level', 'job_family']),
            [
                'properties' => [
                    'team_id' => [
                        'type' => 'integer',
                        'description' => 'Optional: Team-ID (wird auf Root/Elterteam aufgelöst). Default: Team aus Kontext.',
                    ],
                    'status' => [
                        'type' => 'string',
                        'description' => 'Optional: Filter nach status (active/archived/draft). Default: active.',
                    ],
                    'level' => [
                        'type' => 'string',
                        'description' => 'Optional: Filter nach level (junior/mid/senior/lead/principal).',
                    ],
                    'job_family' => [
                        'type' => 'string',
                        'description' => 'Optional: Filter nach job_family (z.B. Engineering, Operations, Sales).',
                    ],
                    'unit_entity_id' => [
                        'type' => 'integer',
                        'description' => 'Optional: nur Stellen dieser Einheit (Firma/Abteilung, Org-Entity-ID).',
                    ],
                    'holder_entity_id' => [
                        'type' => 'integer',
                        'description' => 'Optional: nur Stellen, die dieser Mensch/Agent (Org-Entity-ID) gerade trägt.',
                    ],
                ],
            ]
        );
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $resolved = $this->resolveTeamAndRoot($arguments, $context);
            if ($resolved['error']) {
                return $resolved['error'];
            }
            $rootTeamId = (int) $resolved['root_team_id'];

            $q = JobProfile::query()->where('team_id', $rootTeamId);

            $status = $arguments['status'] ?? 'active';
            if ($status !== null && $status !== '') {
                $q->where('status', $status);
            }
            if (! empty($arguments['level'])) {
                $q->where('level', (string) $arguments['level']);
            }
            if (! empty($arguments['job_family'])) {
                $q->where('job_family', (string) $arguments['job_family']);
            }

            if (! empty($arguments['unit_entity_id'])) {
                $unit = (int) $arguments['unit_entity_id'];
                $ids = array_keys(array_filter(
                    OrganizationLink::entitiesOf('people_job_profile', (clone $q)->pluck('id')->all()),
                    fn ($e) => $e === $unit
                ));
                $q->whereIn('id', $ids ?: [0]);
            }
            if (! empty($arguments['holder_entity_id'])) {
                $held = HeldJobProfile::forEntities([(int) $arguments['holder_entity_id']]);
                $q->whereIn('id', array_map(fn ($jp) => $jp->id, $held) ?: [0]);
            }

            $this->applyStandardFilters($q, $arguments, ['team_id', 'status', 'level', 'job_family', 'created_at']);
            $this->applyStandardSearch($q, $arguments, ['name', 'description', 'content']);
            $this->applyStandardSort($q, $arguments, ['name', 'level', 'status', 'job_family', 'id', 'created_at'], 'name', 'asc');

            $result = $this->applyStandardPaginationResult($q, $arguments);
            $result['data']->load(['skillRecords', 'assignments.employee']);
            $units = OrganizationLink::entitiesOf('people_job_profile', $result['data']->pluck('id')->all());
            $unitNames = $this->entityNames(array_values($units));
            $today = now()->toDateString();
            $items = $result['data']->map(fn (JobProfile $jp) => [
                'id'               => $jp->id,
                'uuid'             => $jp->uuid,
                'name'             => $jp->name,
                'description'      => $jp->description,
                'purpose'          => $jp->purpose,
                'job_family'       => $jp->job_family,
                'level'            => $jp->level,
                'status'           => $jp->status,
                'skills'           => $jp->skills,
                'responsibilities' => $jp->responsibilities,
                'requirements'     => $jp->requirements,
                'soft_skills'      => $jp->soft_skills,
                'kpis'               => $jp->kpis,
                'exclusion_criteria' => $jp->exclusion_criteria,
                'work_model'         => $jp->work_model,
                'reporting'          => $jp->reporting,
                'effective_from'     => $jp->effective_from?->toDateString(),
                'effective_to'       => $jp->effective_to?->toDateString(),
                'skills_structured'  => $jp->skillRecords->map(fn ($s) => [
                    'id' => $s->id, 'name' => $s->name, 'category' => $s->category,
                    'level' => $s->pivot->level, 'is_required' => (bool) $s->pivot->is_required,
                ])->values(),
                'team_id'          => $jp->team_id,
                'unit_entity_id'   => $units[$jp->id] ?? null,
                'unit_name'        => isset($units[$jp->id]) ? ($unitNames[$units[$jp->id]] ?? null) : null,
                'holders'          => $jp->assignments
                    ->filter(fn ($a) => $a->employee
                        && (! $a->valid_from || $a->valid_from->toDateString() <= $today)
                        && (! $a->valid_to || $a->valid_to->toDateString() >= $today))
                    ->map(fn ($a) => [
                        'employee_id'   => $a->employee_id,
                        'name'          => $a->employee->display_name,
                        'kind'          => $a->employee->kind,
                        'org_entity_id' => $a->employee->org_entity_id,
                        'percentage'    => $a->percentage,
                        'is_primary'    => (bool) $a->is_primary,
                    ])->values(),
                'vsm_level'        => $jp->vsm_level ?? [],
            ])->values()->toArray();

            return ToolResult::success([
                'data'         => $items,
                'pagination'   => $result['pagination'] ?? null,
                'team_id'      => $resolved['team_id'],
                'root_team_id' => $rootTeamId,
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Laden der JobProfiles: '.$e->getMessage());
        }
    }

    /** @return array<int,string> Namen der Org-Knoten (guarded — organization optional). */
    private function entityNames(array $ids): array
    {
        if (! $ids) {
            return [];
        }
        try {
            return \Platform\Organization\Models\OrganizationEntity::query()->whereIn('id', array_unique($ids))->pluck('name', 'id')->all();
        } catch (\Throwable $e) {
            return [];
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'read',
            'tags'          => ['people', 'job_profiles', 'lookup'],
            'read_only'     => true,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level'    => 'safe',
            'idempotent'    => true,
        ];
    }
}
