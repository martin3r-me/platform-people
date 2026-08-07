<?php

namespace Platform\People\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\People\Models\Employee;
use Platform\People\Tools\Concerns\ResolvesPeopleTeam;

class ListEmployeeSkillsTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesPeopleTeam;

    public function getName(): string
    {
        return 'people.employee_skills.GET';
    }

    public function getDescription(): string
    {
        return 'GET /people/employee-skills - Listet alle Skills eines Mitarbeiters.';
    }

    public function getSchema(): array
    {
        return $this->mergeWriteSchema([
            'properties' => [
                'team_id'     => ['type' => 'integer', 'description' => 'Optional: Team-ID.'],
                'employee_id' => ['type' => 'integer', 'description' => 'ID des Mitarbeiters (ERFORDERLICH).'],
            ],
            'required' => ['employee_id'],
        ]);
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
                $arguments, $context, 'employee_id',
                Employee::class, 'NOT_FOUND', 'Mitarbeiter nicht gefunden.'
            );
            if ($found['error']) {
                return $found['error'];
            }

            $employee = $found['model'];
            if ((int) $employee->team_id !== $rootTeamId) {
                return ToolResult::error('ACCESS_DENIED', 'Mitarbeiter gehört nicht zum Team.');
            }

            $employeeSkills = $employee->skills()->with('skill')->get();
            $items = $employeeSkills->map(fn ($es) => [
                'id'           => $es->id,
                'skill_id'     => (int) $es->skill_id,
                'skill_name'   => $es->skill?->name,
                'category'     => $es->skill?->category,
                'level'        => $es->level,
                'certified_at' => $es->certified_at?->format('Y-m-d'),
                'notes'        => $es->notes,
            ])->values()->toArray();

            return ToolResult::success([
                'employee_id' => $employee->id,
                'data'        => $items,
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: '.$e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'read',
            'tags'          => ['people', 'employees', 'skills'],
            'read_only'     => true,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level'    => 'safe',
            'idempotent'    => true,
        ];
    }
}
