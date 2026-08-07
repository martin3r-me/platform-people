<?php

namespace Platform\People\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\People\Models\EmployeeJobProfile;
use Platform\People\Tools\Concerns\ResolvesPeopleTeam;

class DeleteEmployeeJobProfileTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesPeopleTeam;

    public function getName(): string
    {
        return 'people.employee_job_profiles.DELETE';
    }

    public function getDescription(): string
    {
        return 'DELETE /people/employee-job-profiles/{id} - Entfernt eine JobProfile-Zuweisung von einem Mitarbeiter (soft delete).';
    }

    public function getSchema(): array
    {
        return $this->mergeWriteSchema([
            'properties' => [
                'team_id'                 => ['type' => 'integer'],
                'employee_job_profile_id' => ['type' => 'integer', 'description' => 'ERFORDERLICH: ID der Zuweisung.'],
            ],
            'required' => ['employee_job_profile_id'],
        ]);
    }

    protected function getAccessAction(): string
    {
        return 'delete';
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $resolved = $this->resolveTeamAndRoot($arguments, $context);
            if ($resolved['error']) {
                return $resolved['error'];
            }
            $rootTeamId = (int) $resolved['root_team_id'];

            $found = $this->validateAndFindModel(
                $arguments,
                $context,
                'employee_job_profile_id',
                EmployeeJobProfile::class,
                'NOT_FOUND',
                'Mitarbeiter-JobProfile-Zuweisung nicht gefunden.'
            );
            if ($found['error']) {
                return $found['error'];
            }

            /** @var EmployeeJobProfile $a */
            $a = $found['model'];
            if ((int) $a->team_id !== $rootTeamId) {
                return ToolResult::error('ACCESS_DENIED', 'Zuweisung gehört nicht zum Root/Elterteam des angegebenen Teams.');
            }

            $a->delete();

            return ToolResult::success([
                'id'      => $a->id,
                'message' => 'Zuweisung gelöscht (soft delete).',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Löschen: '.$e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'action',
            'tags'          => ['people', 'employee_job_profiles', 'delete'],
            'read_only'     => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level'    => 'write',
            'idempotent'    => true,
        ];
    }
}
