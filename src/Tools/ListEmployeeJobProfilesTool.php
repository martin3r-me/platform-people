<?php

namespace Platform\People\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardGetOperations;
use Platform\People\Models\EmployeeJobProfile;
use Platform\People\Tools\Concerns\ResolvesPeopleTeam;

class ListEmployeeJobProfilesTool implements ToolContract, ToolMetadataContract
{
    use HasStandardGetOperations;
    use ResolvesPeopleTeam;

    public function getName(): string
    {
        return 'people.employee_job_profiles.GET';
    }

    public function getDescription(): string
    {
        return 'GET /people/employee-job-profiles - Listet JobProfile-Zuweisungen an Mitarbeiter. Filter: employee_id, job_profile_id.';
    }

    public function getSchema(): array
    {
        return $this->mergeSchemas(
            $this->getStandardGetSchema(['team_id', 'employee_id', 'job_profile_id']),
            [
                'properties' => [
                    'team_id'        => ['type' => 'integer'],
                    'employee_id'    => ['type' => 'integer', 'description' => 'Optional: Nur Zuweisungen eines Mitarbeiters.'],
                    'job_profile_id' => ['type' => 'integer', 'description' => 'Optional: Nur Zuweisungen eines bestimmten JobProfiles.'],
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

            $q = EmployeeJobProfile::query()
                ->with(['employee:id,display_name', 'jobProfile:id,name,level'])
                ->where('team_id', $rootTeamId);

            if (! empty($arguments['employee_id'])) {
                $q->where('employee_id', (int) $arguments['employee_id']);
            }
            if (! empty($arguments['job_profile_id'])) {
                $q->where('job_profile_id', (int) $arguments['job_profile_id']);
            }

            $this->applyStandardFilters($q, $arguments, ['team_id', 'employee_id', 'job_profile_id', 'is_primary', 'created_at']);
            $this->applyStandardSort($q, $arguments, ['id', 'percentage', 'valid_from', 'created_at'], 'id', 'desc');

            $result = $this->applyStandardPaginationResult($q, $arguments);
            $items = $result['data']->map(fn (EmployeeJobProfile $a) => [
                'id'               => $a->id,
                'employee_id'      => $a->employee_id,
                'employee_name'    => $a->employee?->display_name,
                'job_profile_id'   => $a->job_profile_id,
                'job_profile_name' => $a->jobProfile?->name,
                'level'            => $a->jobProfile?->level,
                'percentage'       => $a->percentage,
                'is_primary'       => (bool) $a->is_primary,
                'valid_from'       => $a->valid_from?->toDateString(),
                'valid_to'         => $a->valid_to?->toDateString(),
                'note'             => $a->note,
            ])->values()->toArray();

            return ToolResult::success([
                'data'         => $items,
                'pagination'   => $result['pagination'] ?? null,
                'team_id'      => $resolved['team_id'],
                'root_team_id' => $rootTeamId,
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Laden der Mitarbeiter-JobProfile-Zuweisungen: '.$e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'read',
            'tags'          => ['people', 'employee_job_profiles', 'lookup'],
            'read_only'     => true,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level'    => 'safe',
            'idempotent'    => true,
        ];
    }
}
