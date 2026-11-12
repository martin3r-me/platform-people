<?php

namespace Platform\People\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\People\Models\Employee;
use Platform\People\Tools\Concerns\ResolvesPeopleTeam;

class CreateEmployeeTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesPeopleTeam;

    public function getName(): string
    {
        return 'people.employees.POST';
    }

    public function getDescription(): string
    {
        return 'POST /people/employees - Führt einen Menschen als Mitarbeiter. Der Mitarbeiter ist eine Facette an seinem '
            . 'Personen-Knoten in der Organisation (org_entity_id, Pflicht — die Organisation kennt die Person). '
            . 'Agenten nicht hier anlegen, sondern über people.employees.adopt_agents.POST.';
    }

    public function getSchema(): array
    {
        return $this->mergeWriteSchema([
            'properties' => [
                'team_id'         => ['type' => 'integer', 'description' => 'Optional: Team-ID.'],
                'org_entity_id'   => ['type' => 'integer', 'description' => 'ERFORDERLICH: Personen-Knoten (Org-Entity vom Typ Person).'],
                'display_name'    => ['type' => 'string', 'description' => 'Optional: Anzeigename. Default: Name des Knotens.'],
                'employee_number' => ['type' => 'string', 'description' => 'Optional: Personalnummer.'],
                'user_id'         => ['type' => 'integer', 'description' => 'Optional: Platform-User der Person.'],
                'status'          => ['type' => 'string', 'enum' => ['active', 'inactive', 'left'], 'description' => 'Optional. Default: active.'],
            ],
            'required' => ['org_entity_id'],
        ]);
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $resolved = $this->resolveTeamAndRoot($arguments, $context);
            if ($resolved['error']) {
                return $resolved['error'];
            }
            $teamId = (int) $resolved['root_team_id'];
            $entityId = (int) ($arguments['org_entity_id'] ?? 0);

            $node = \Platform\Organization\Models\OrganizationEntity::query()->forTeam($teamId)->persons()->find($entityId);
            if (! $node) {
                return ToolResult::error('VALIDATION_ERROR', "org_entity_id {$entityId} ist kein Personen-Knoten dieses Teams.");
            }
            $existing = Employee::withTrashed()->forTeam($teamId)->where('org_entity_id', $entityId)->first();
            if ($existing) {
                return ToolResult::error('CONFLICT', "Für diesen Knoten gibt es schon den Mitarbeiter #{$existing->id}"
                    . ($existing->trashed() ? ' (gelöscht).' : '.'));
            }

            $employee = new Employee();
            $employee->fill([
                'display_name'    => trim((string) ($arguments['display_name'] ?? '')) ?: (string) $node->name,
                'kind'            => 'human',
                'employee_number' => ($arguments['employee_number'] ?? null) ?: null,
                'user_id'         => ! empty($arguments['user_id']) ? (int) $arguments['user_id'] : null,
                'status'          => in_array($arguments['status'] ?? 'active', ['active', 'inactive', 'left'], true) ? ($arguments['status'] ?? 'active') : 'active',
                'org_entity_id'   => $entityId,
            ]);
            $employee->team_id = $teamId;
            $employee->save();

            return ToolResult::success([
                'id'            => $employee->id,
                'display_name'  => $employee->display_name,
                'kind'          => $employee->kind,
                'status'        => $employee->status,
                'org_entity_id' => $employee->org_entity_id,
                'user_id'       => $employee->user_id,
                'message'       => 'Mitarbeiter angelegt.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'action',
            'tags'          => ['people', 'employees', 'create'],
            'read_only'     => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level'    => 'write',
            'idempotent'    => false,
        ];
    }
}
