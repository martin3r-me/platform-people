<?php

namespace Platform\People\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\People\Services\AgentAdoption;
use Platform\People\Tools\Concerns\ResolvesPeopleTeam;

class AdoptAgentsTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesPeopleTeam;

    public function getName(): string
    {
        return 'people.employees.adopt_agents.POST';
    }

    public function getDescription(): string
    {
        return 'POST /people/employees/adopt-agents - Führt alle Agenten (Engrams) der Organisation, die noch keinen '
            . 'Mitarbeiter-Satz haben, als Mitarbeiter (kind=agent): Employee mit Org-Knoten und User plus Anstellung '
            . 'ab Geburt. Idempotent — schon geführte (auch gelöschte) Agenten werden übersprungen.';
    }

    public function getSchema(): array
    {
        return $this->mergeWriteSchema([
            'properties' => [
                'team_id' => ['type' => 'integer', 'description' => 'Optional: Team-ID.'],
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

            $created = app(AgentAdoption::class)->adopt((int) $resolved['root_team_id']);

            return ToolResult::success([
                'adopted' => count($created),
                'data'    => array_map(fn ($e) => [
                    'id'            => $e->id,
                    'display_name'  => $e->display_name,
                    'org_entity_id' => $e->org_entity_id,
                    'user_id'       => $e->user_id,
                ], $created),
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'action',
            'tags'          => ['people', 'employees', 'agents', 'create'],
            'read_only'     => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level'    => 'write',
            'idempotent'    => true,
        ];
    }
}
