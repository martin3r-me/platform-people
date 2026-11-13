<?php

namespace Platform\People\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\People\Models\Employee;
use Platform\People\Services\DevelopmentCard;
use Platform\People\Tools\Concerns\ResolvesPeopleTeam;

/**
 * Entwicklungs-Karten lesen: Reifestufe, Alter, Erfahrung und Handlungsbedarf je Mitarbeiter (Menschen und
 * Agenten) — dieselbe Sicht wie das People-Cockpit.
 */
class GetDevelopmentTool implements ToolContract, ToolMetadataContract
{
    use ResolvesPeopleTeam;

    public function getName(): string
    {
        return 'people.development.GET';
    }

    public function getDescription(): string
    {
        return 'GET /people/development - Entwicklungs-Karten der aktiven Mitarbeiter: Reifestufe (onboarding/growing/stable/carrying), '
            . 'Alter (seit Anstellung), Erfahrung (Ergebnisse gesamt/30 Tage/Vormonat, Qualität, Korrekturen, Regeln) und Handlungsbedarf '
            . '(critical/warning/info mit Begründung), dringendster zuerst. Optional employee_id für eine Karte, kind, only_needs.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'team_id' => ['type' => 'integer', 'description' => 'Optional: Team-ID.'],
                'employee_id' => ['type' => 'integer', 'description' => 'Optional: nur die Karte dieses Mitarbeiters.'],
                'kind' => ['type' => 'string', 'enum' => Employee::KINDS, 'description' => 'Optional: nur Menschen (human) oder Agenten (agent).'],
                'only_needs' => ['type' => 'boolean', 'description' => 'Optional: nur Karten mit Handlungsbedarf über „info“ hinaus.'],
            ],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $resolved = $this->resolveTeamAndRoot($arguments, $context);
            if ($resolved['error']) {
                return $resolved['error'];
            }
            $teamId = (int) $resolved['root_team_id'];
            $service = new DevelopmentCard();

            if (! empty($arguments['employee_id'])) {
                $employee = Employee::forTeam($teamId)->find((int) $arguments['employee_id']);
                if (! $employee) {
                    return ToolResult::error('NOT_FOUND', 'Mitarbeiter nicht gefunden. Nutze people.employees.GET.');
                }

                return ToolResult::success(['card' => $service->forEmployee($employee)]);
            }

            $cards = collect($service->forTeam($teamId))
                ->when($arguments['kind'] ?? null, fn ($c, $k) => $c->where('kind', $k))
                ->when(! empty($arguments['only_needs']), fn ($c) => $c->filter(
                    fn ($card) => collect($card['needs'])->contains(fn ($n) => $n['severity'] !== 'info')))
                ->values()->all();

            return ToolResult::success([
                'stages' => DevelopmentCard::STAGES,
                'count' => count($cards),
                'cards' => $cards,
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Laden der Entwicklungs-Karten: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'query',
            'tags' => ['people', 'development', 'maturity', 'agents', 'employees', 'hr'],
            'read_only' => true,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'read',
            'idempotent' => true,
        ];
    }
}
