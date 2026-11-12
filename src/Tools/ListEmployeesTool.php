<?php

namespace Platform\People\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\People\Models\Employee;
use Platform\People\Services\AgentAdoption;
use Platform\People\Tools\Concerns\ResolvesPeopleTeam;

class ListEmployeesTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesPeopleTeam;

    public function getName(): string
    {
        return 'people.employees.GET';
    }

    public function getDescription(): string
    {
        return 'GET /people/employees - Listet Mitarbeiter (Menschen und Agenten) mit Art, Status, Org-Knoten, User, '
            . 'Anstellung (Beginn = bei Agenten die Geburt) und Zahl der Skills. Zeigt außerdem, wie viele Agenten der '
            . 'Organisation noch nicht als Mitarbeiter geführt sind (people.employees.adopt_agents.POST).';
    }

    public function getSchema(): array
    {
        return $this->mergeWriteSchema([
            'properties' => [
                'team_id' => ['type' => 'integer', 'description' => 'Optional: Team-ID.'],
                'kind'    => ['type' => 'string', 'enum' => Employee::KINDS, 'description' => 'Optional: nur Menschen (human) oder Agenten (agent).'],
                'status'  => ['type' => 'string', 'enum' => ['active', 'inactive', 'left'], 'description' => 'Optional: Status-Filter.'],
                'search'  => ['type' => 'string', 'description' => 'Optional: Teil des Namens.'],
            ],
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

            $employees = Employee::forTeam($rootTeamId)
                ->withCount('skills')
                ->with(['employments' => fn ($q) => $q->orderByDesc('started_on')])
                ->when($arguments['kind'] ?? null, fn ($q, $k) => $q->where('kind', $k))
                ->when($arguments['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
                ->when($arguments['search'] ?? null, fn ($q, $s) => $q->where('display_name', 'like', '%' . $s . '%'))
                ->orderBy('display_name')
                ->get();

            $items = $employees->map(function (Employee $e) {
                $emp = $e->employments->first();

                return [
                    'id'             => $e->id,
                    'display_name'   => $e->display_name,
                    'kind'           => $e->kind,
                    'status'         => $e->status,
                    'org_entity_id'  => $e->org_entity_id,
                    'user_id'        => $e->user_id,
                    'skills_count'   => (int) $e->skills_count,
                    'employment'     => $emp ? [
                        'type'       => $emp->employment_type,
                        'started_on' => $emp->started_on?->format('Y-m-d'),
                        'status'     => $emp->status,
                    ] : null,
                ];
            })->values()->toArray();

            return ToolResult::success([
                'data'             => $items,
                'agents_to_adopt'  => array_map(fn ($a) => ['org_entity_id' => (int) $a->id, 'name' => (string) $a->name],
                    app(AgentAdoption::class)->pending($rootTeamId)),
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'read',
            'tags'          => ['people', 'employees', 'agents'],
            'read_only'     => true,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level'    => 'safe',
            'idempotent'    => true,
        ];
    }
}
