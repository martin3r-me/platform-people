<?php

namespace Platform\People\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\People\Models\Employee;
use Platform\People\Tools\Concerns\ResolvesPeopleTeam;

class UpdateEmployeeTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesPeopleTeam;

    public function getName(): string
    {
        return 'people.employees.PATCH';
    }

    public function getDescription(): string
    {
        return 'PATCH /people/employees/{id} - Ändert Anzeigename, Personalnummer, User oder Status eines Mitarbeiters. '
            . 'Der Personen-Knoten (org_entity_id) bleibt fest — wer die Person ist, weiß die Organisation.';
    }

    public function getSchema(): array
    {
        return $this->mergeWriteSchema([
            'properties' => [
                'team_id'         => ['type' => 'integer', 'description' => 'Optional: Team-ID.'],
                'employee_id'     => ['type' => 'integer', 'description' => 'ERFORDERLICH: ID des Mitarbeiters.'],
                'display_name'    => ['type' => 'string'],
                'employee_number' => ['type' => 'string', 'description' => 'Leer = entfernen.'],
                'user_id'         => ['type' => 'integer', 'description' => '0 = Verknüpfung entfernen.'],
                'status'          => ['type' => 'string', 'enum' => ['active', 'inactive', 'left']],
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
            $employee = Employee::forTeam((int) $resolved['root_team_id'])->find((int) ($arguments['employee_id'] ?? 0));
            if (! $employee) {
                return ToolResult::error('NOT_FOUND', 'Mitarbeiter nicht gefunden.');
            }

            if (array_key_exists('display_name', $arguments)) {
                $name = trim((string) $arguments['display_name']);
                if ($name === '') {
                    return ToolResult::error('VALIDATION_ERROR', 'display_name darf nicht leer sein.');
                }
                $employee->display_name = $name;
            }
            if (array_key_exists('employee_number', $arguments)) {
                $employee->employee_number = trim((string) $arguments['employee_number']) ?: null;
            }
            if (array_key_exists('user_id', $arguments)) {
                $employee->user_id = (int) $arguments['user_id'] > 0 ? (int) $arguments['user_id'] : null;
            }
            if (array_key_exists('status', $arguments)) {
                if (! in_array($arguments['status'], ['active', 'inactive', 'left'], true)) {
                    return ToolResult::error('VALIDATION_ERROR', 'status muss active, inactive oder left sein.');
                }
                $employee->status = $arguments['status'];
            }
            $employee->save();

            return ToolResult::success([
                'id'              => $employee->id,
                'display_name'    => $employee->display_name,
                'kind'            => $employee->kind,
                'status'          => $employee->status,
                'employee_number' => $employee->employee_number,
                'user_id'         => $employee->user_id,
                'message'         => 'Mitarbeiter aktualisiert.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'action',
            'tags'          => ['people', 'employees', 'update'],
            'read_only'     => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level'    => 'write',
            'idempotent'    => true,
        ];
    }
}
