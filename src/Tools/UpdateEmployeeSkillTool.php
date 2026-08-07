<?php

namespace Platform\People\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\People\Models\Employee;
use Platform\People\Models\EmployeeSkill;
use Platform\People\Tools\Concerns\ResolvesPeopleTeam;

class UpdateEmployeeSkillTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesPeopleTeam;

    public function getName(): string
    {
        return 'people.employee_skills.PATCH';
    }

    public function getDescription(): string
    {
        return 'PATCH /people/employee-skills - Aktualisiert die Skill-Zuordnung eines Mitarbeiters (level, certified_at, notes).';
    }

    public function getSchema(): array
    {
        return $this->mergeWriteSchema([
            'properties' => [
                'team_id'           => ['type' => 'integer', 'description' => 'Optional: Team-ID.'],
                'employee_skill_id' => ['type' => 'integer', 'description' => 'Optional: ID der EmployeeSkill-Zuordnung. Alternativ employee_id + skill_id angeben.'],
                'employee_id'       => ['type' => 'integer', 'description' => 'Optional: ID des Mitarbeiters (zusammen mit skill_id, falls employee_skill_id nicht angegeben).'],
                'skill_id'          => ['type' => 'integer', 'description' => 'Optional: ID des Skills (zusammen mit employee_id, falls employee_skill_id nicht angegeben).'],
                'level'             => ['type' => 'string', 'description' => 'Optional: basic/advanced/expert.'],
                'certified_at'      => ['type' => 'string', 'description' => 'Optional: Datum (YYYY-MM-DD). "" zum Leeren.'],
                'notes'             => ['type' => 'string', 'description' => 'Optional: Anmerkungen. "" zum Leeren.'],
            ],
            'required' => [],
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

            /** @var EmployeeSkill|null $employeeSkill */
            $employeeSkill = null;

            if (! empty($arguments['employee_skill_id'])) {
                $employeeSkill = EmployeeSkill::find((int) $arguments['employee_skill_id']);
                if (! $employeeSkill) {
                    return ToolResult::error('NOT_FOUND', 'EmployeeSkill-Zuordnung nicht gefunden.');
                }
            } else {
                $employeeId = (int) ($arguments['employee_id'] ?? 0);
                $skillId    = (int) ($arguments['skill_id'] ?? 0);
                if ($employeeId <= 0 || $skillId <= 0) {
                    return ToolResult::error('VALIDATION_ERROR', 'Entweder employee_skill_id ODER employee_id + skill_id angeben.');
                }
                $employeeSkill = EmployeeSkill::query()
                    ->where('employee_id', $employeeId)
                    ->where('skill_id', $skillId)
                    ->first();
                if (! $employeeSkill) {
                    return ToolResult::error('NOT_FOUND', 'Skill ist diesem Mitarbeiter nicht zugeordnet.');
                }
            }

            $employee = Employee::find((int) $employeeSkill->employee_id);
            if (! $employee || (int) $employee->team_id !== $rootTeamId) {
                return ToolResult::error('ACCESS_DENIED', 'Zuordnung gehört nicht zum Team.');
            }

            $update = [];
            if (array_key_exists('level', $arguments)) {
                $level = $arguments['level'];
                if (! in_array($level, ['basic', 'advanced', 'expert'])) {
                    return ToolResult::error('VALIDATION_ERROR', 'level muss basic, advanced oder expert sein.');
                }
                $update['level'] = $level;
            }
            if (array_key_exists('certified_at', $arguments)) {
                $val = (string) ($arguments['certified_at'] ?? '');
                $update['certified_at'] = $val === '' ? null : $val;
            }
            if (array_key_exists('notes', $arguments)) {
                $val = (string) ($arguments['notes'] ?? '');
                $update['notes'] = $val === '' ? null : $val;
            }

            if (! empty($update)) {
                $employeeSkill->update($update);
            }

            return ToolResult::success([
                'id'          => $employeeSkill->id,
                'employee_id' => (int) $employeeSkill->employee_id,
                'skill_id'    => (int) $employeeSkill->skill_id,
                'message'     => 'Mitarbeiter-Skill-Zuordnung aktualisiert.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: '.$e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'action',
            'tags'          => ['people', 'employees', 'skills', 'update'],
            'read_only'     => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level'    => 'write',
            'idempotent'    => true,
        ];
    }
}
