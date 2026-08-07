<?php

namespace Platform\People\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\People\Models\Employee;
use Platform\People\Tools\Concerns\ResolvesPeopleTeam;

class RemoveEmployeeSkillTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesPeopleTeam;

    public function getName(): string
    {
        return 'people.employee_skills.DELETE';
    }

    public function getDescription(): string
    {
        return 'DELETE /people/employee-skills - Entfernt einen Skill von einem Mitarbeiter.';
    }

    public function getSchema(): array
    {
        return $this->mergeWriteSchema([
            'properties' => [
                'team_id'     => ['type' => 'integer', 'description' => 'Optional: Team-ID.'],
                'employee_id' => ['type' => 'integer', 'description' => 'ID des Mitarbeiters (ERFORDERLICH).'],
                'skill_id'    => ['type' => 'integer', 'description' => 'ID des Skills (ERFORDERLICH).'],
            ],
            'required' => ['employee_id', 'skill_id'],
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

            $skillId = (int) ($arguments['skill_id'] ?? 0);
            $employeeSkill = $employee->skills()->where('skill_id', $skillId)->first();
            if (! $employeeSkill) {
                return ToolResult::error('NOT_FOUND', 'Skill ist diesem Mitarbeiter nicht zugeordnet.');
            }

            $employeeSkill->delete();

            return ToolResult::success([
                'employee_id' => $employee->id,
                'skill_id'    => $skillId,
                'message'     => 'Skill-Zuordnung entfernt.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: '.$e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'action',
            'tags'          => ['people', 'employees', 'skills', 'delete'],
            'read_only'     => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level'    => 'write',
            'idempotent'    => true,
        ];
    }
}
