<?php

namespace Platform\People\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\People\Models\Employee;
use Platform\People\Services\ImpactScore;
use Platform\People\Tools\Concerns\ResolvesPeopleTeam;

/**
 * Wirkung lesen: Wirkungs-Score je Mitarbeiter (Menschen und Agenten) im rollierenden Quartal — Leisten,
 * Einzahlen, Querdenken, Zielgüte, dazu die Teilwerte zum Gegensteuern und der Handlungsbedarf.
 */
class GetImpactTool implements ToolContract, ToolMetadataContract
{
    use ResolvesPeopleTeam;

    public function getName(): string
    {
        return 'people.impact.GET';
    }

    public function getDescription(): string
    {
        return 'GET /people/impact - Wirkungs-Score der aktiven Mitarbeiter im rollierenden Quartal (90 Tage) im Sinne der Organisation: '
            . 'parts.deliver (selbst geschätzte, dokumentierte, abgenommene Story Points; Wiederöffnen zieht ab), parts.contribute (auf Key Results '
            . 'einzahlend + nachträglich bei erreichten Meilensteinen der Transformation Map), parts.lateral (Querdenken, das die Organisation '
            . 'aufgriff), parts.goal_quality (Zielgüte eigener OKRs, Optimum ~80 % Erreichung). indicators: Anteile geschätzt/dokumentiert/'
            . 'ausgerichtet, calibration (sagen Story Points den Aufwand voraus), goal_attainment. needs = Handlungsbedarf; trend = gegen das Quartal davor. '
            . 'Optional employee_id, kind.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'team_id' => ['type' => 'integer', 'description' => 'Optional: Team-ID.'],
                'employee_id' => ['type' => 'integer', 'description' => 'Optional: nur die Karte dieses Mitarbeiters.'],
                'kind' => ['type' => 'string', 'enum' => Employee::KINDS, 'description' => 'Optional: nur Menschen (human) oder Agenten (agent).'],
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
            $service = new ImpactScore();

            if (! empty($arguments['employee_id'])) {
                $employee = Employee::forTeam($teamId)->find((int) $arguments['employee_id']);
                if (! $employee) {
                    return ToolResult::error('NOT_FOUND', 'Mitarbeiter nicht gefunden. Nutze people.employees.GET.');
                }

                return ToolResult::success(['impact' => $service->forEmployee($employee)]);
            }

            $rows = collect($service->forTeam($teamId))
                ->when($arguments['kind'] ?? null, fn ($c, $k) => $c->where('kind', $k))
                ->values()->all();

            return ToolResult::success([
                'sources' => $service->sources(),
                'count' => count($rows),
                'impact' => $rows,
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Berechnen der Wirkung: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'query',
            'tags' => ['people', 'impact', 'okr', 'story-points', 'agents', 'employees', 'hr'],
            'read_only' => true,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'read',
            'idempotent' => true,
        ];
    }
}
