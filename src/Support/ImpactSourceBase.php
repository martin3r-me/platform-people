<?php

namespace Platform\People\Support;

use Carbon\CarbonInterface;
use Platform\People\Contracts\ImpactSource;

/**
 * Leere Quelle: ein Modul überschreibt nur die Facetten, die es kennt.
 */
abstract class ImpactSourceBase implements ImpactSource
{
    public function work(int $teamId, array $subjects, CarbonInterface $from, CarbonInterface $to): array
    {
        return [];
    }

    public function alignment(int $teamId, array $contextRefs): array
    {
        return [];
    }

    public function goals(int $teamId, array $subjects, CarbonInterface $from, CarbonInterface $to): array
    {
        return [];
    }

    public function milestones(int $teamId, CarbonInterface $from, CarbonInterface $to): array
    {
        return [];
    }

    public function adoptions(int $teamId, CarbonInterface $from, CarbonInterface $to): array
    {
        return [];
    }

    public function transparency(int $teamId, array $subjects, CarbonInterface $from, CarbonInterface $to): array
    {
        return [];
    }
}
