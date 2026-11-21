<?php

namespace Platform\People\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Platform\People\Models\Employee;
use Platform\People\Models\ImpactSnapshot;
use Platform\People\Services\ImpactScore;

/** Hält den Wirkungs-Score aller aktiven Mitarbeiter einmal am Tag fest (Verlauf). */
class ImpactSnapshotCommand extends Command
{
    protected $signature = 'people:impact-snapshot {--team= : nur dieses Team}';

    protected $description = 'Wirkungs-Score je Mitarbeiter für heute festhalten';

    public function handle(ImpactScore $score): int
    {
        $teams = $this->option('team')
            ? [(int) $this->option('team')]
            : Employee::where('status', 'active')->distinct()->pluck('team_id')->filter()->map(fn ($v) => (int) $v)->all();
        $today = Carbon::today()->toDateString();
        $n = 0;
        foreach ($teams as $teamId) {
            try {
                $rows = $score->forTeam($teamId);
            } catch (\Throwable $e) {
                report($e);
                $this->warn("Team {$teamId}: {$e->getMessage()}");

                continue;
            }
            foreach ($rows as $r) {
                ImpactSnapshot::updateOrCreate(
                    ['employee_id' => $r['employee_id'], 'taken_on' => $today],
                    ['team_id' => $teamId, 'score' => $r['score'], 'parts' => $r['parts'], 'indicators' => $r['indicators']],
                );
                $n++;
            }
        }
        $this->info("{$n} Wirkungs-Snapshots für {$today}.");

        return self::SUCCESS;
    }
}
