<?php

namespace Platform\People\Services;

/**
 * WIRKUNGS-SCORE — Wirkung eines Mitarbeiters im Sinne der Organisation (Mensch und Agent gleich).
 *
 * Gesagt wird nur, WAS zählt — nie, wie man es erreicht. Vier Teile, keiner lässt sich allein ausreizen:
 *
 *   Leisten (S1)      selbst geschätzte, dokumentierte, abgenommene Komplexität (Story Points). Nicht
 *                     geschätzt oder nicht dokumentiert zählt 0; wieder geöffnet zieht ab.
 *   Einzahlen (S3/S4) Arbeit, die auf Ziele (Key Results) einzahlt, wiegt mehr — und wird NACHTRÄGLICH
 *                     belohnt, wenn ein Meilenstein der Transformation Map erreicht wird, auf den sie einzahlte.
 *   Querdenken (S4)   Arbeit ohne Ziel-Bezug zählt voll bis zum Erkundungsanteil, darüber immer weniger;
 *                     greift die Organisation etwas davon auf (Beschluss, Key Result, Aufgabe …), zählt es
 *                     rückwirkend deutlich höher.
 *   Zielgüte (S3)     eigene Ziele sollen ambitioniert sein: das Optimum liegt bei ~70–85 % Erreichung;
 *                     dauernd 100 % heißt, die Ziele waren zu leicht. Gezählt werden abgeschlossene Zyklen.
 *
 * Rein rechnend (keine DB) — die Daten kommen aus den Quellen (ImpactSource). Die Stellgrößen stehen oben;
 * justiert wird an den echten Zahlen.
 */
class ImpactCalculator
{
    public const EXPLORATION_SHARE = 0.2;   // Anteil eigener Leistung, der ohne Ziel-Bezug voll zählt
    public const ALIGNED_BONUS = 0.5;       // Ziel-bezogene Arbeit zählt 1,5-fach
    public const MILESTONE_CREDIT = 1.0;    // Nachträgliche Anerkennung je Punkt bei erreichtem Meilenstein (× Gewicht)
    public const ADOPTION_FACTOR = 2.0;     // Aufgegriffenes zählt zusätzlich so viel × seine Punkte
    public const ADOPTION_DEFAULT_POINTS = 3;
    public const NO_GOALS_FACTOR = 0.75;    // ohne eigene Ziele fehlt die Richtung
    public const NEED_SHARE = 0.3;          // ab diesem Anteil ungeschätzt/undokumentiert → Handlungsbedarf
    public const CALIBRATION_MIN = 5;

    /** Zielgüte über Zielerreichung: Stützpunkte, dazwischen linear. */
    public const GOAL_CURVE = [[0.0, 0.0], [0.4, 0.2], [0.7, 1.0], [0.85, 1.0], [1.0, 0.6]];

    public static function goalQuality(float $attainment): float
    {
        $a = max(0.0, min(1.0, $attainment));
        $pts = self::GOAL_CURVE;
        for ($i = 1; $i < count($pts); $i++) {
            [$x0, $y0] = $pts[$i - 1];
            [$x1, $y1] = $pts[$i];
            if ($a <= $x1) {
                return $y0 + ($y1 - $y0) * ($a - $x0) / ($x1 - $x0);
            }
        }

        return end($pts)[1];
    }

    /**
     * @param  array<int,int>  $employeeIds
     * @param  array  $work        Zeilen aus ImpactSource::work()
     * @param  array  $alignment   context_ref → [['ref','weight']]
     * @param  array  $goals       Zeilen aus ImpactSource::goals()
     * @param  array  $milestones  Zeilen aus ImpactSource::milestones()
     * @param  array  $adoptions   Zeilen aus ImpactSource::adoptions()
     * @return array<int,array> employee_id → Ergebnis
     */
    public function calculate(array $employeeIds, array $work, array $alignment, array $goals, array $milestones, array $adoptions): array
    {
        // Meilenstein-Anerkennung: Ziel-Ref → stärkstes Gewicht über alle erreichten Meilensteine
        $milestoneWeight = [];
        foreach ($milestones as $m) {
            foreach ($m['contributors'] ?? [] as $c) {
                $milestoneWeight[$c['ref']] = max($milestoneWeight[$c['ref']] ?? 0.0, (float) $c['weight']);
            }
        }
        $adoptedFrom = [];
        foreach ($adoptions as $a) {
            $adoptedFrom[$a['origin_ref']][] = $a;
        }

        $byEmployee = [];
        foreach ($work as $w) {
            $byEmployee[$w['employee_id']][] = $w;
        }
        $goalsByEmployee = [];
        foreach ($goals as $g) {
            $goalsByEmployee[$g['employee_id']][] = $g;
        }

        $out = [];
        foreach ($employeeIds as $id) {
            $out[$id] = $this->forEmployee($byEmployee[$id] ?? [], $goalsByEmployee[$id] ?? [], $alignment, $milestoneWeight, $adoptedFrom);
        }

        return $out;
    }

    private function forEmployee(array $items, array $goals, array $alignment, array $milestoneWeight, array $adoptedFrom): array
    {
        $done = array_values(array_filter($items, fn ($w) => empty($w['reopened'])));
        $reopened = array_values(array_filter($items, fn ($w) => ! empty($w['reopened'])));

        $aligned = $unaligned = $contribute = $lateral = 0.0;
        $estimated = $documented = $alignedCount = 0;
        $adopted = [];
        $pairs = [];
        foreach ($done as $w) {
            $points = $w['points'];
            $estimated += $points !== null ? 1 : 0;
            $documented += ! empty($w['documented']) ? 1 : 0;
            $targets = $this->targetsOf($w['context_refs'] ?? [], $alignment);
            $alignedCount += $targets ? 1 : 0;

            // Aufgegriffen zählt auch, wenn das Stück selbst ungeschätzt war — die Organisation hat es gewollt
            foreach ($adoptedFrom[$w['ref']] ?? [] as $a) {
                $lateral += self::ADOPTION_FACTOR * ($points ?? self::ADOPTION_DEFAULT_POINTS);
                $adopted[] = ['ref' => $w['ref'], 'title' => $w['title'] ?? null, 'by' => $a['ref'], 'by_title' => $a['title'] ?? null];
            }
            if ($points === null || empty($w['documented'])) {
                continue; // ungeschätzt oder undokumentiert: keine Wirkung
            }
            if ($w['minutes'] ?? null) {
                $pairs[] = [$points, (int) $w['minutes']];
            }
            if ($targets) {
                $aligned += $points;
                $contribute += self::ALIGNED_BONUS * $points;
                $credit = 0.0;
                foreach ($targets as $t) {
                    $credit = max($credit, $milestoneWeight[$t['ref']] ?? 0.0);
                }
                $contribute += self::MILESTONE_CREDIT * $credit * $points;
            } else {
                $unaligned += $points;
            }
        }

        // Erkundungsanteil: ohne Ziel-Bezug bis EXPLORATION_SHARE der eigenen Leistung voll, darüber Wurzel
        $budget = $aligned * self::EXPLORATION_SHARE / (1 - self::EXPLORATION_SHARE);
        $exploration = min($unaligned, $budget) + sqrt(max(0.0, $unaligned - $budget));
        $deliver = $aligned + $exploration;
        foreach ($reopened as $w) {
            $deliver -= (float) ($w['points'] ?? 0);
        }

        // Zielgüte nur über abgeschlossene Ziele — ein laufendes Quartal steht anfangs naturgemäß bei 0 %.
        // Laufen nur Ziele, ist die Richtung da (neutral); fehlen eigene Ziele ganz, fehlt sie.
        // Nie eingecheckte Ziele sind nicht verfehlt, sondern nicht gepflegt — eigener Hinweis, keine Zielgüte.
        $unmaintained = array_values(array_filter($goals, fn ($g) => array_key_exists('maintained', $g) && ! $g['maintained']));
        $closed = array_values(array_filter($goals, fn ($g) => ! empty($g['closed']) && ($g['maintained'] ?? true)));
        $goalQuality = null;
        if ($closed) {
            $goalQuality = array_sum(array_map(fn ($g) => self::goalQuality((float) $g['attainment']), $closed)) / count($closed);
        }
        $factor = match (true) {
            $goalQuality !== null => 0.5 + 0.5 * $goalQuality,
            (bool) $goals => 1.0,
            default => self::NO_GOALS_FACTOR,
        };
        $score = max(0.0, ($deliver + $contribute + $lateral) * $factor);

        $n = count($done);
        $indicators = [
            'estimated' => $n ? $estimated / $n : null,
            'documented' => $n ? $documented / $n : null,
            'aligned' => $n ? $alignedCount / $n : null,
            'calibration' => count($pairs) >= self::CALIBRATION_MIN ? $this->spearman($pairs) : null,
            'goal_attainment' => $closed ? array_sum(array_column($closed, 'attainment')) / count($closed) : null,
        ];

        return [
            'score' => round($score, 1),
            'parts' => [
                'deliver' => round($deliver, 1),
                'contribute' => round($contribute, 1),
                'lateral' => round($lateral, 1),
                'goal_quality' => $goalQuality === null ? null : round($goalQuality, 2),
            ],
            'indicators' => array_map(fn ($v) => $v === null ? null : round($v, 2), $indicators),
            'counts' => ['done' => $n, 'reopened' => count($reopened), 'goals' => count($goals), 'goals_closed' => count($closed), 'goals_unmaintained' => count($unmaintained), 'adopted' => count($adopted)],
            'adopted' => $adopted,
            'needs' => $this->needs($n, $indicators, $goals, count($unmaintained)),
        ];
    }

    /** Ziele, auf die eines der Kontexte einzahlt. */
    private function targetsOf(array $contextRefs, array $alignment): array
    {
        $out = [];
        foreach ($contextRefs as $ref) {
            foreach ($alignment[$ref] ?? [] as $t) {
                $out[$t['ref']] = $t;
            }
        }

        return array_values($out);
    }

    private function needs(int $n, array $ind, array $goals, int $unmaintained = 0): array
    {
        $needs = [];
        if ($unmaintained > 0) {
            $needs[] = ['key' => 'goals_unmaintained', 'severity' => 'warning',
                'text' => $unmaintained === 1
                    ? '1 eigenes Ziel nie eingecheckt — nicht gepflegt.'
                    : sprintf('%d eigene Ziele nie eingecheckt — nicht gepflegt.', $unmaintained)];
        }
        if ($n > 0 && $ind['estimated'] !== null && 1 - $ind['estimated'] > self::NEED_SHARE) {
            $needs[] = ['key' => 'unestimated', 'severity' => 'warning',
                'text' => sprintf('%d %% der erledigten Arbeit ohne Story Points — zählt nicht als Wirkung.', round(100 * (1 - $ind['estimated'])))];
        }
        if ($n > 0 && $ind['documented'] !== null && 1 - $ind['documented'] > self::NEED_SHARE) {
            $needs[] = ['key' => 'undocumented', 'severity' => 'warning',
                'text' => sprintf('%d %% der erledigten Arbeit nicht dokumentiert — nicht nachvollziehbar.', round(100 * (1 - $ind['documented'])))];
        }
        if (! $goals) {
            $needs[] = ['key' => 'no_goals', 'severity' => 'warning', 'text' => 'Keine eigenen Ziele (OKR) — die Richtung fehlt.'];
        } elseif ($ind['goal_attainment'] !== null && $ind['goal_attainment'] >= 0.95) {
            $needs[] = ['key' => 'goals_too_easy', 'severity' => 'info', 'text' => 'Ziele fast vollständig erreicht — ambitionierter setzen (Optimum ~80 %).'];
        } elseif ($ind['goal_attainment'] !== null && $ind['goal_attainment'] < 0.4) {
            $needs[] = ['key' => 'goals_missed', 'severity' => 'warning', 'text' => 'Ziele weit verfehlt — Ziele oder Fokus prüfen.'];
        }
        if ($n >= self::CALIBRATION_MIN && $ind['aligned'] !== null && $ind['aligned'] < 0.5) {
            $needs[] = ['key' => 'unaligned', 'severity' => 'info', 'text' => 'Mehr als die Hälfte der Arbeit zahlt auf kein Ziel ein.'];
        }
        if ($ind['calibration'] !== null && $ind['calibration'] < 0.3) {
            $needs[] = ['key' => 'calibration', 'severity' => 'info', 'text' => 'Story Points sagen den Aufwand kaum voraus — Schätzung kalibrieren.'];
        }

        return $needs;
    }

    /** Rangkorrelation Punkte ↔ Minuten: sagt die Schätzung den Aufwand voraus? */
    private function spearman(array $pairs): float
    {
        $rank = function (array $xs): array {
            $sorted = $xs;
            asort($sorted);
            $ranks = [];
            $i = 0;
            $vals = array_values($sorted);
            $keys = array_keys($sorted);
            while ($i < count($vals)) {
                $j = $i;
                while ($j + 1 < count($vals) && $vals[$j + 1] == $vals[$i]) {
                    $j++;
                }
                for ($k = $i; $k <= $j; $k++) {
                    $ranks[$keys[$k]] = ($i + $j) / 2 + 1;
                }
                $i = $j + 1;
            }
            ksort($ranks);

            return array_values($ranks);
        };
        $rx = $rank(array_column($pairs, 0));
        $ry = $rank(array_column($pairs, 1));
        $n = count($rx);
        $mx = array_sum($rx) / $n;
        $my = array_sum($ry) / $n;
        $cov = $vx = $vy = 0.0;
        for ($i = 0; $i < $n; $i++) {
            $cov += ($rx[$i] - $mx) * ($ry[$i] - $my);
            $vx += ($rx[$i] - $mx) ** 2;
            $vy += ($ry[$i] - $my) ** 2;
        }

        return ($vx > 0 && $vy > 0) ? $cov / sqrt($vx * $vy) : 0.0;
    }
}
