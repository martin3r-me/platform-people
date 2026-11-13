<?php

namespace Platform\People\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\People\Models\AgentRule;
use Platform\People\Models\Employee;
use Platform\People\Tools\Concerns\ResolvesPeopleTeam;

/**
 * Die Arbeitsweise eines Agenten als Regeln lesen (Spiegel aus dem Engram) — Grundlage, um gezielt
 * Kuratier-Entwürfe zu einzelnen Regeln anzulegen (people.agent_curations.POST).
 */
class ListAgentRulesTool implements ToolContract, ToolMetadataContract
{
    use ResolvesPeopleTeam;

    public function getName(): string
    {
        return 'people.agent_rules.GET';
    }

    public function getDescription(): string
    {
        return 'GET /people/agents/{entity_id}/rules - Listet die Regeln der Arbeitsweise eines Agenten (Engram): rule_id, Art (step/rule/avoid), Anlass (trigger, leer = Kern), Text, Status, Bewährung. Standard: nur geltende Regeln (active/probation).';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'team_id' => ['type' => 'integer', 'description' => 'Optional: Team-ID. Default: Team aus Kontext.'],
                'entity_id' => ['type' => 'integer', 'description' => 'ERFORDERLICH: Org-Entity-ID des Agenten.'],
                'status' => ['type' => 'string', 'description' => 'Optional: live (Standard: active+probation) | proposed | history (superseded+retired) | all.'],
            ],
            'required' => ['entity_id'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $resolved = $this->resolveTeamAndRoot($arguments, $context);
            if ($resolved['error']) {
                return $resolved['error'];
            }
            $agent = Employee::forTeam((int) $resolved['root_team_id'])->agents()
                ->where('org_entity_id', (int) ($arguments['entity_id'] ?? 0))->first();
            if (! $agent) {
                return ToolResult::error('NOT_FOUND', 'Agent nicht gefunden. Nutze people.employees.GET (kind=agent).');
            }
            $statuses = match ($arguments['status'] ?? 'live') {
                'proposed' => ['proposed'],
                'history' => ['superseded', 'retired'],
                'all' => null,
                default => AgentRule::LIVE,
            };
            $q = AgentRule::where('org_entity_id', $agent->org_entity_id)->orderBy('ord')->orderBy('rule_id');
            if ($statuses) {
                $q->whereIn('status', $statuses);
            }

            return ToolResult::success([
                'agent' => ['entity_id' => (int) $agent->org_entity_id, 'employee_id' => $agent->id, 'name' => $agent->display_name],
                'rules' => $q->get()->map(fn ($r) => [
                    'rule_id' => $r->rule_id,
                    'kind' => $r->kind,
                    'trigger' => $r->trigger,
                    'text' => $r->text,
                    'why' => $r->why,
                    'status' => $r->status,
                    'confirmed' => $r->confirmed,
                    'refuted' => $r->refuted,
                    'supersedes' => $r->supersedes ?: null,
                ])->values()->all(),
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Laden der Regeln: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'query',
            'tags' => ['people', 'agents', 'rules', 'memory'],
            'read_only' => true,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'read',
            'idempotent' => true,
        ];
    }
}
