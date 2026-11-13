<?php

namespace Platform\People\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\People\Models\AgentCuration;
use Platform\People\Models\AgentRule;
use Platform\People\Models\Employee;
use Platform\People\Tools\Concerns\ResolvesPeopleTeam;

/**
 * Einen Kuratier-ENTWURF für die Arbeitsweise eines Agenten anlegen. Er geht erst nach Freigabe durch einen
 * Menschen (Reiter „Gedächtnis“ am Mitarbeiter) an das Engram.
 */
class CreateAgentCurationDraftTool implements ToolContract, ToolMetadataContract
{
    use ResolvesPeopleTeam;

    public function getName(): string
    {
        return 'people.agent_curations.POST';
    }

    public function getDescription(): string
    {
        return 'POST /people/agents/{entity_id}/curations - Legt einen Kuratier-ENTWURF für die Arbeitsweise eines Agenten an (wird erst nach Freigabe im Reiter „Gedächtnis“ am Mitarbeiter an den Agenten geschickt). '
            . 'op=revise (rule_id + text: neue Fassung löst die alte ab), op=retire (rule_id: stilllegen), op=add (text + kind step|rule|avoid + optional trigger: neue Regel). '
            . 'note = Begründung, die Mensch und Agent sehen. Regeln vorher mit people.agent_rules.GET lesen.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'team_id' => ['type' => 'integer', 'description' => 'Optional: Team-ID. Default: Team aus Kontext.'],
                'entity_id' => ['type' => 'integer', 'description' => 'ERFORDERLICH: Org-Entity-ID des Agenten.'],
                'op' => ['type' => 'string', 'enum' => ['revise', 'retire', 'add'], 'description' => 'ERFORDERLICH: revise | retire | add.'],
                'rule_id' => ['type' => 'integer', 'description' => 'Für revise/retire: die Regel-Kennung (rN → N).'],
                'text' => ['type' => 'string', 'description' => 'Für revise/add: der Regeltext.'],
                'kind' => ['type' => 'string', 'enum' => ['step', 'rule', 'avoid'], 'description' => 'Für add: step = Schritt im Ablauf, rule = Vorgehen, avoid = bewusst nicht tun.'],
                'trigger' => ['type' => 'string', 'description' => 'Für add: Anlass, bei dem die Regel gilt (leer = Kern, in jedem Takt).'],
                'note' => ['type' => 'string', 'description' => 'ERFORDERLICH: Begründung (ein bis zwei Sätze).'],
            ],
            'required' => ['entity_id', 'op', 'note'],
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
            $entityId = (int) $agent->org_entity_id;
            $op = (string) ($arguments['op'] ?? '');
            $note = trim((string) ($arguments['note'] ?? ''));
            $text = trim((string) ($arguments['text'] ?? ''));
            if ($note === '') {
                return ToolResult::error('VALIDATION_ERROR', 'note (Begründung) ist erforderlich.');
            }

            if ($op === 'add') {
                $kind = (string) ($arguments['kind'] ?? '');
                if ($text === '' || ! in_array($kind, ['step', 'rule', 'avoid'], true)) {
                    return ToolResult::error('VALIDATION_ERROR', 'add braucht text und kind (step|rule|avoid).');
                }
                $path = 'rules';
                $baseSha = str_repeat('0', 64);
                $content = json_encode(['kind' => $kind, 'trigger' => trim((string) ($arguments['trigger'] ?? '')), 'text' => $text], JSON_UNESCAPED_UNICODE);
                $rule = null;
            } elseif (in_array($op, ['revise', 'retire'], true)) {
                $rule = AgentRule::where('org_entity_id', $entityId)
                    ->where('rule_id', (int) ($arguments['rule_id'] ?? 0))->first();
                if (! $rule || ! $rule->isLive()) {
                    return ToolResult::error('NOT_FOUND', 'Geltende Regel nicht gefunden. Nutze people.agent_rules.GET.');
                }
                if ($op === 'revise' && $text === '') {
                    return ToolResult::error('VALIDATION_ERROR', 'revise braucht text (die neue Fassung).');
                }
                $path = 'rules/' . $rule->rule_id;
                $baseSha = $rule->sha;
                $content = $op === 'revise' ? $text : null;
            } else {
                return ToolResult::error('VALIDATION_ERROR', 'op muss revise, retire oder add sein.');
            }

            $c = AgentCuration::create([
                'org_entity_id' => $entityId,
                'path' => $path,
                'op' => $op,
                'base_sha' => $baseSha,
                'content' => $content,
                'note' => $note,
                'status' => 'draft',
                'created_by_user_id' => $context->user?->id,
            ]);

            return ToolResult::success([
                'id' => $c->id,
                'agent' => $agent->display_name,
                'op' => $op,
                'rule_id' => $rule?->rule_id,
                'status' => 'draft',
                'message' => 'Entwurf angelegt — wartet auf Freigabe im Reiter „Gedächtnis“ am Mitarbeiter.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Anlegen des Entwurfs: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['people', 'agents', 'rules', 'memory', 'curation', 'draft'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'write',
            'idempotent' => false,
        ];
    }
}
