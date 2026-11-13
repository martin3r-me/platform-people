<?php

namespace Platform\People\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Platform\People\Models\AgentCuration;
use Platform\People\Models\AgentRule;
use Platform\People\Models\Employee;
use Platform\People\Models\EmployeeJobProfile;

/**
 * ENTWICKLUNGS-KARTE — die HR-Sicht auf einen Mitarbeiter: wie alt, wie erfahren, wie reif, wo Handlungsbedarf.
 * Gleiche Brille für Menschen und Agenten; bei Agenten kommen Leistung (organization: AgentEffectiveness)
 * und Gedächtnis (people: Regeln, Kuratier-Aufträge) dazu. Menschen haben noch keine Leistungsdaten — bei
 * ihnen zählen vorerst die Stammdaten.
 *
 * Die Schwellen sind bewusst grob und stehen oben: justiert wird an den echten Zahlen der Flotte.
 */
class DevelopmentCard
{
    /** Reifestufen in Reihenfolge. */
    public const STAGES = [
        'onboarding' => 'Einarbeitung',
        'growing' => 'Wachsend',
        'stable' => 'Stabil',
        'carrying' => 'Tragend',
    ];

    // Einarbeitung: jünger als … oder weniger Ergebnisse als …
    private const ONBOARDING_WEEKS = 4;
    private const ONBOARDING_RESULTS = 10;
    // Wachsend: jünger als … oder weniger Ergebnisse / geltende Regeln als …
    private const GROWING_WEEKS = 12;
    private const GROWING_RESULTS = 50;
    private const GROWING_RULES = 5;
    // Tragend: so viele Ergebnisse, diese Qualität (30 Tage) und kein Handlungsbedarf der Stufe „warning“ aufwärts
    private const CARRYING_RESULTS = 150;
    private const CARRYING_QUALITY = 0.9;

    // Handlungsbedarf
    private const IDLE_DAYS = 14;
    private const QUALITY_MIN = 0.75;
    private const QUALITY_MIN_SAMPLE = 8;
    private const CORRECTIONS_30D = 3;
    private const DROP_FACTOR = 0.5;
    private const DROP_MIN_PREVIOUS = 10;
    private const COST_FACTOR = 2.0;
    private const PROBATION_RULES = 10;

    private const SEVERITY_WEIGHT = ['critical' => 3, 'warning' => 2, 'info' => 1];

    /** Karten aller aktiven Mitarbeiter des Teams, dringendster Handlungsbedarf zuerst. */
    public function forTeam(int $teamId): array
    {
        $employees = Employee::forTeam($teamId)->where('status', 'active')
            ->with(['employments' => fn ($q) => $q->orderBy('started_on')])
            ->orderBy('display_name')->get();

        return $this->build($employees);
    }

    public function forEmployee(Employee $employee): array
    {
        $employee->loadMissing(['employments' => fn ($q) => $q->orderBy('started_on')]);

        return $this->build(collect([$employee]))[0];
    }

    /** @param Collection<int,Employee> $employees */
    private function build(Collection $employees): array
    {
        $now = now();
        $agentEntityIds = $employees->filter(fn (Employee $e) => $e->isAgent() && $e->org_entity_id)
            ->pluck('org_entity_id')->map(fn ($v) => (int) $v)->values()->all();

        $perf = $this->performance($employees, $agentEntityIds, $now);
        $memory = $this->memory($agentEntityIds);
        $positions = $this->positions($employees->pluck('id')->all());
        $fleetCost = $this->medianCost($perf['last30']);

        $cards = [];
        foreach ($employees as $e) {
            $cards[] = $this->card($e, $now, $perf, $memory, $positions[$e->id] ?? [], $fleetCost);
        }
        usort($cards, fn ($a, $b) => [$b['need_score'], $a['name']] <=> [$a['need_score'], $b['name']]);

        return $cards;
    }

    private function card(Employee $e, Carbon $now, array $perf, array $memory, array $positions, ?int $fleetCost): array
    {
        $started = $e->employments->first()?->started_on ?? $e->created_at;
        $ageDays = $started ? (int) $started->diffInDays($now) : 0;
        $isAgent = $e->isAgent() && $e->org_entity_id;
        $entityId = (int) $e->org_entity_id;

        $needs = [];
        $this->positionNeeds($positions, $needs);

        $experience = null;
        $stage = null;
        if ($isAgent) {
            $life = $perf['lifetime'][$entityId] ?? null;
            $m30 = $perf['last30'][$entityId] ?? null;
            $p30 = $perf['prev30'][$entityId] ?? null;
            $m14 = $perf['last14'][$entityId] ?? null;
            $mem = $memory[$entityId] ?? ['live' => 0, 'probation' => 0, 'proposed' => 0, 'drafts' => 0, 'pending' => 0];

            $quality = $this->quality($m30);
            $experience = [
                'results_total' => $life['done'] ?? null,
                'hours_total' => $life['hours'] ?? null,
                'results_30d' => $m30['done'] ?? null,
                'results_prev_30d' => $p30['done'] ?? null,
                'quality_30d' => $quality,
                'corrections_30d' => $m30['corrections'] ?? null,
                'tokens_per_result' => $m30['tokens_per_result'] ?? null,
                'rules_live' => $mem['live'],
                'rules_probation' => $mem['probation'],
                'drafts' => $mem['drafts'],
                'pending_orders' => $mem['pending'],
            ];
            if ($perf['available']) {
                $this->agentNeeds($ageDays, $m30, $p30, $m14, $quality, $fleetCost, $needs);
            }
            $this->memoryNeeds($mem, $needs);
            $stage = $perf['available'] ? $this->stage($ageDays, (int) ($life['done'] ?? 0), $mem['live'], $quality, $needs) : null;
        } else {
            $this->humanNeeds($e, $needs);
        }

        usort($needs, fn ($a, $b) => self::SEVERITY_WEIGHT[$b['severity']] <=> self::SEVERITY_WEIGHT[$a['severity']]);

        return [
            'employee_id' => $e->id,
            'name' => $e->display_name,
            'kind' => $e->kind,
            'org_entity_id' => $e->org_entity_id,
            'started_on' => $started?->toDateString(),
            'age_days' => $ageDays,
            'age_label' => $this->ageLabel($ageDays),
            'position' => $positions[0]['name'] ?? null,
            'stage' => $stage,
            'stage_label' => $stage ? self::STAGES[$stage] : null,
            'experience' => $experience,
            'needs' => $needs,
            'need_score' => array_sum(array_map(fn ($n) => self::SEVERITY_WEIGHT[$n['severity']], $needs)),
        ];
    }

    private function stage(int $ageDays, int $resultsTotal, int $rulesLive, ?float $quality, array $needs): string
    {
        $weeks = intdiv($ageDays, 7);
        if ($weeks < self::ONBOARDING_WEEKS || $resultsTotal < self::ONBOARDING_RESULTS) {
            return 'onboarding';
        }
        if ($weeks < self::GROWING_WEEKS || $resultsTotal < self::GROWING_RESULTS || $rulesLive < self::GROWING_RULES) {
            return 'growing';
        }
        $serious = collect($needs)->contains(fn ($n) => $n['severity'] !== 'info');
        if ($resultsTotal >= self::CARRYING_RESULTS && $quality !== null && $quality >= self::CARRYING_QUALITY && ! $serious) {
            return 'carrying';
        }

        return 'stable';
    }

    /** Anteil der Ergebnisse an allem, was ein Ergebnis hätte sein sollen (abgelehnt, liegengelassen, korrigiert). */
    private function quality(?array $m): ?float
    {
        if (! $m) {
            return null;
        }
        $bad = (int) $m['declined'] + (int) $m['dropped'] + (int) $m['corrections'];
        $all = (int) $m['done'] + $bad;

        return $all >= self::QUALITY_MIN_SAMPLE ? round($m['done'] / $all, 2) : null;
    }

    private function agentNeeds(int $ageDays, ?array $m30, ?array $p30, ?array $m14, ?float $quality, ?int $fleetCost, array &$needs): void
    {
        if ($ageDays >= self::IDLE_DAYS && $m14 && (int) $m14['done'] === 0) {
            $needs[] = $this->need('idle', 'warning', 'Seit ' . self::IDLE_DAYS . ' Tagen kein Ergebnis');
        }
        if ($quality !== null && $quality < self::QUALITY_MIN) {
            $bad = (int) $m30['declined'] + (int) $m30['dropped'] + (int) $m30['corrections'];
            $needs[] = $this->need('quality', 'warning', "Viel geht daneben: {$bad} abgelehnt, liegengelassen oder korrigiert bei {$m30['done']} Ergebnissen (30 Tage)");
        }
        if ($m30 && (int) $m30['corrections'] >= self::CORRECTIONS_30D) {
            $needs[] = $this->need('corrections', 'warning', "{$m30['corrections']} Korrekturen am Gedächtnis in 30 Tagen — die Arbeitsweise sitzt noch nicht");
        }
        if ($m30 && $p30 && (int) $p30['done'] >= self::DROP_MIN_PREVIOUS && $m30['done'] < $p30['done'] * self::DROP_FACTOR) {
            $needs[] = $this->need('drop', 'warning', "Leistung eingebrochen: {$m30['done']} Ergebnisse statt {$p30['done']} im Monat davor");
        }
        if ($fleetCost && ($m30['tokens_per_result'] ?? null) && $m30['tokens_per_result'] > $fleetCost * self::COST_FACTOR) {
            $needs[] = $this->need('cost', 'info', 'Teuer: ' . number_format($m30['tokens_per_result'], 0, ',', '.') . ' Token je Ergebnis, mehr als doppelt so viel wie der Flotten-Median');
        }
    }

    private function memoryNeeds(array $mem, array &$needs): void
    {
        if ($mem['drafts'] > 0) {
            $needs[] = $this->need('drafts', 'warning', $mem['drafts'] === 1 ? '1 Entwurf zur Arbeitsweise wartet auf Freigabe' : "{$mem['drafts']} Entwürfe zur Arbeitsweise warten auf Freigabe");
        }
        if ($mem['proposed'] > 0 && $mem['live'] === 0) {
            $needs[] = $this->need('import', 'warning', "Umzug auf Regeln wartet auf Freigabe ({$mem['proposed']} vorgeschlagene Regeln)");
        }
        if ($mem['probation'] >= self::PROBATION_RULES) {
            $needs[] = $this->need('probation', 'info', "{$mem['probation']} Regeln auf Probe — bewähren lassen oder freigeben");
        }
    }

    private function positionNeeds(array $positions, array &$needs): void
    {
        if ($positions === []) {
            $needs[] = $this->need('no_position', 'critical', 'Keine Stelle — ohne Stelle fehlt der Maßstab');

            return;
        }
        $sum = array_sum(array_column($positions, 'percentage'));
        if ($sum !== 100) {
            $needs[] = $this->need('allocation', 'warning', "Stellenanteile ergeben {$sum} % statt 100 %");
        }
        if (empty($positions[0]['has_kpis'])) {
            $needs[] = $this->need('no_kpis', 'info', 'Hauptstelle ohne Kennzahlen — woran wird Erfolg gemessen?');
        }
    }

    private function humanNeeds(Employee $e, array &$needs): void
    {
        if (! $e->user_id) {
            $needs[] = $this->need('no_user', 'info', 'Kein Benutzer verknüpft');
        }
        if ($e->employments->isEmpty()) {
            $needs[] = $this->need('no_employment', 'info', 'Keine Anstellung hinterlegt');
        }
    }

    private function need(string $key, string $severity, string $text): array
    {
        return ['key' => $key, 'severity' => $severity, 'text' => $text];
    }

    /** Gültige Stellen je Mitarbeiter, Hauptstelle zuerst. @return array<int, list<array>> */
    private function positions(array $employeeIds): array
    {
        if (! $employeeIds) {
            return [];
        }
        $today = now()->toDateString();
        $out = [];
        EmployeeJobProfile::query()->whereIn('employee_id', $employeeIds)
            ->where(fn ($q) => $q->whereNull('valid_from')->orWhere('valid_from', '<=', $today))
            ->where(fn ($q) => $q->whereNull('valid_to')->orWhere('valid_to', '>=', $today))
            ->whereHas('jobProfile', fn ($q) => $q->where('status', 'active'))
            ->with('jobProfile')
            ->orderByDesc('is_primary')->orderByDesc('percentage')->orderByDesc('id')
            ->get()
            ->each(function (EmployeeJobProfile $a) use (&$out) {
                $out[$a->employee_id][] = [
                    'name' => $a->jobProfile->name,
                    'percentage' => (int) $a->percentage,
                    'has_kpis' => is_array($a->jobProfile->kpis) && $a->jobProfile->kpis !== [],
                ];
            });

        return $out;
    }

    /** Leistung der Agenten aus organization — guarded; ohne organization bleibt die Karte bei den Stammdaten. */
    private function performance(Collection $employees, array $agentEntityIds, Carbon $now): array
    {
        $empty = ['available' => false, 'lifetime' => [], 'last30' => [], 'prev30' => [], 'last14' => []];
        if (! $agentEntityIds || ! class_exists(\Platform\Organization\Services\AgentEffectiveness::class)) {
            return $empty;
        }
        try {
            $svc = app(\Platform\Organization\Services\AgentEffectiveness::class);
            $earliest = $employees->filter(fn ($e) => $e->isAgent())
                ->map(fn ($e) => $e->employments->first()?->started_on ?? $e->created_at)->filter()->min() ?? $now->copy()->subYear();
            $key = fn (array $rows) => collect($rows)->keyBy('entity_id')->only($agentEntityIds)->all();

            return [
                'available' => true,
                'lifetime' => $key($svc->period(Carbon::parse($earliest)->startOfDay(), $now, false)),
                'last30' => $key($svc->period($now->copy()->subDays(30), $now, true)),
                'prev30' => $key($svc->period($now->copy()->subDays(60), $now->copy()->subDays(30), false)),
                'last14' => $key($svc->period($now->copy()->subDays(self::IDLE_DAYS), $now, false)),
            ];
        } catch (\Throwable $e) {
            report($e);

            return $empty;
        }
    }

    /** Regeln und Kuratier-Stand je Agent. */
    private function memory(array $entityIds): array
    {
        if (! $entityIds) {
            return [];
        }
        $out = array_fill_keys($entityIds, ['live' => 0, 'probation' => 0, 'proposed' => 0, 'drafts' => 0, 'pending' => 0]);
        AgentRule::whereIn('org_entity_id', $entityIds)->whereIn('status', ['active', 'probation', 'proposed'])
            ->selectRaw('org_entity_id, status, count(*) as n')->groupBy('org_entity_id', 'status')->get()
            ->each(function ($r) use (&$out) {
                $id = (int) $r->org_entity_id;
                $n = (int) $r->n;
                if (in_array($r->status, AgentRule::LIVE, true)) {
                    $out[$id]['live'] += $n;
                }
                if ($r->status === 'probation') {
                    $out[$id]['probation'] = $n;
                }
                if ($r->status === 'proposed') {
                    $out[$id]['proposed'] = $n;
                }
            });
        AgentCuration::whereIn('org_entity_id', $entityIds)->whereIn('status', ['draft', 'pending'])
            ->selectRaw('org_entity_id, status, count(*) as n')->groupBy('org_entity_id', 'status')->get()
            ->each(function ($r) use (&$out) {
                $out[(int) $r->org_entity_id][$r->status === 'draft' ? 'drafts' : 'pending'] = (int) $r->n;
            });

        return $out;
    }

    private function medianCost(array $last30): ?int
    {
        $vals = collect($last30)->pluck('tokens_per_result')->filter()->sort()->values();

        return $vals->isEmpty() ? null : (int) $vals[intdiv($vals->count(), 2)];
    }

    private function ageLabel(int $days): string
    {
        if ($days < 14) {
            return $days === 1 ? '1 Tag' : "{$days} Tage";
        }
        if ($days < 120) {
            return intdiv($days, 7) . ' Wochen';
        }
        $months = intdiv($days, 30);

        return $months < 24 ? "{$months} Monate" : intdiv($months, 12) . ' Jahre';
    }
}
