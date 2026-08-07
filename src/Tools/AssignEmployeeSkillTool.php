<?php

namespace Platform\People\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\People\Models\Employee;
use Platform\People\Models\EmployeeSkill;
use Platform\People\Models\Skill;
use Platform\People\Tools\Concerns\ResolvesPeopleTeam;

class AssignEmployeeSkillTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesPeopleTeam;

    public function getName(): string
    {
        return 'people.employee_skills.POST';
    }

    public function getDescription(): string
    {
        return 'POST /people/employee-skills - Ordnet einen Skill einem Mitarbeiter zu.';
    }

    public function getSchema(): array
    {
        return $this->mergeWriteSchema([
            'properties' => [
                'team_id'      => ['type' => 'integer', 'description' => 'Optional: Team-ID.'],
                'employee_id'  => ['type' => 'integer', 'description' => 'ID des Mitarbeiters (ERFORDERLICH).'],
                'skill_id'     => ['type' => 'integer', 'description' => 'ID des Skills (ERFORDERLICH).'],
                'level'        => ['type' => 'string', 'description' => 'Optional: basic/advanced/expert. Default: basic.'],
                'certified_at' => ['type' => 'string', 'description' => 'Optional: Zertifizierungsdatum (YYYY-MM-DD).'],
                'notes'        => ['type' => 'string', 'description' => 'Optional: Anmerkungen.'],
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
            $skill = Skill::find($skillId);
            if (! $skill || (int) $skill->team_id !== $rootTeamId) {
                return ToolResult::error('NOT_FOUND', 'Skill nicht gefunden oder gehört nicht zum Team.');
            }

            if ($employee->skills()->where('skill_id', $skillId)->exists()) {
                return ToolResult::error('VALIDATION_ERROR', 'Skill ist diesem Mitarbeiter bereits zugeordnet.');
            }

            $level = $arguments['level'] ?? 'basic';
            if (! in_array($level, ['basic', 'advanced', 'expert'])) {
                $level = 'basic';
            }

            $employeeSkill = EmployeeSkill::create([
                'team_id'      => (int) $employee->team_id,
                'employee_id'  => $employee->id,
                'skill_id'     => $skillId,
                'level'        => $level,
                'certified_at' => ($arguments['certified_at'] ?? null) ?: null,
                'notes'        => ($arguments['notes'] ?? null) ?: null,
            ]);

            return ToolResult::success([
                'id'           => $employeeSkill->id,
                'employee_id'  => $employee->id,
                'skill_id'     => $skillId,
                'level'        => $level,
                'message'      => 'Skill erfolgreich zugeordnet.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: '.$e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'action',
            'tags'          => ['people', 'employees', 'skills', 'assign'],
            'read_only'     => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level'    => 'write',
            'idempotent'    => false,
        ];
    }
}
