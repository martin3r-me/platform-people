<?php

namespace Platform\People\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Platform\People\Models\Employee;

/**
 * WIRKUNG je Mitarbeiter (Mensch und Agent): sammelt bei allen angemeldeten Quellen (ImpactSourceRegistry)
 * und rechnet mit dem ImpactCalculator. people kennt keine der Quellen; fehlt eine, fehlt nur ihr Teil.
 *
 * Fenster = rollierendes Quartal (OKR-Takt); zum Vergleich das Quartal davor.
 */
class ImpactScore
{
    public const WINDOW_DAYS = 90;

    public function __construct(
        private ?ImpactSourceRegistry $registry = null,
        private ?ImpactCalculator $calculator = null,
    ) {
        $this->registry ??= app(ImpactSourceRegistry::class);
        $this->calculator ??= new ImpactCalculator();
    }

    /** Ergebnisse aller aktiven Mitarbeiter des Teams, höchste Wirkung zuerst. */
    public function forTeam(int $teamId): array
    {
        $employees = Employee::forTeam($teamId)->where('status', 'active')->orderBy('display_name')->get();
        $rows = $this->build($teamId, $employees);
        usort($rows, fn ($a, $b) => $b['score'] <=> $a['score']);

        return $rows;
    }

    public function forEmployee(Employee $employee): array
    {
        return $this->build((int) $employee->team_id, collect([$employee]))[0];
    }

    /** Welche Quellen gerade Wirkung melden. */
    public function sources(): array
    {
        return array_keys($this->registry->all());
    }

    /** @param Collection<int,Employee> $employees */
    private function build(int $teamId, Collection $employees): array
    {
        $to = Carbon::now();
        $from = $to->copy()->subDays(self::WINDOW_DAYS);
        $subjects = $this->subjects($employees);
        $ids = $employees->pluck('id')->map(fn ($v) => (int) $v)->all();

        $current = $this->window($teamId, $subjects, $ids, $from, $to);
        $previous = $this->window($teamId, $subjects, $ids, $from->copy()->subDays(self::WINDOW_DAYS), $from);

        $out = [];
        foreach ($employees as $e) {
            $r = $current[$e->id];
            $prev = $previous[$e->id]['score'];
            $out[] = ['employee_id' => $e->id, 'name' => $e->display_name, 'kind' => $e->kind]
                + $r
                + ['previous_score' => $prev, 'trend' => round($r['score'] - $prev, 1),
                    'window' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
                    'sources' => $this->sources()];
        }

        return $out;
    }

    private function window(int $teamId, array $subjects, array $ids, Carbon $from, Carbon $to): array
    {
        $work = $goals = $milestones = $adoptions = [];
        foreach ($this->registry->all() as $source) {
            $work = array_merge($work, $this->safe(fn () => $source->work($teamId, $subjects, $from, $to)));
            $goals = array_merge($goals, $this->safe(fn () => $source->goals($teamId, $subjects, $from, $to)));
            $milestones = array_merge($milestones, $this->safe(fn () => $source->milestones($teamId, $from, $to)));
            $adoptions = array_merge($adoptions, $this->safe(fn () => $source->adoptions($teamId, $from, $to)));
        }
        $contextRefs = array_values(array_unique(array_merge(...array_map(fn ($w) => $w['context_refs'] ?? [], $work ?: [[]]))));
        $alignment = [];
        if ($contextRefs) {
            foreach ($this->registry->all() as $source) {
                foreach ($this->safe(fn () => $source->alignment($teamId, $contextRefs)) as $ref => $targets) {
                    $alignment[$ref] = array_merge($alignment[$ref] ?? [], $targets);
                }
            }
        }

        return $this->calculator->calculate($ids, $work, $alignment, $goals, $milestones, $adoptions);
    }

    /** Mitarbeiter → Subjekte der Quellen. Agenten handeln über ihren Bot-User am Org-Knoten. */
    private function subjects(Collection $employees): array
    {
        $linked = [];
        $entityIds = $employees->pluck('org_entity_id')->filter()->map(fn ($v) => (int) $v)->all();
        if ($entityIds && class_exists(\Platform\Organization\Models\OrganizationEntity::class)) {
            $linked = $this->safe(fn () => \Platform\Organization\Models\OrganizationEntity::whereIn('id', $entityIds)
                ->pluck('linked_user_id', 'id')->filter()->all());
        }

        return $employees->map(fn (Employee $e) => [
            'employee_id' => (int) $e->id,
            'user_id' => $e->user_id ? (int) $e->user_id : (isset($linked[$e->org_entity_id]) ? (int) $linked[$e->org_entity_id] : null),
            'entity_id' => $e->org_entity_id ? (int) $e->org_entity_id : null,
        ])->values()->all();
    }

    /** Eine kaputte Quelle darf den Score nicht kippen — ihr Teil fehlt dann. */
    private function safe(callable $fn): array
    {
        try {
            return (array) $fn();
        } catch (\Throwable $e) {
            report($e);

            return [];
        }
    }
}
