<?php

namespace Platform\People\Services;

use Platform\People\Contracts\ImpactSource;

/**
 * ImpactSourceRegistry — die Module, die Wirkung melden (Planner, OKR, Management Book …).
 * Jedes registriert sich selbst (guarded); people kennt keines davon.
 */
class ImpactSourceRegistry
{
    /** @var array<string,ImpactSource> */
    protected array $sources = [];

    public function register(ImpactSource $source): void
    {
        $this->sources[$source->key()] = $source;
    }

    /** @return array<string,ImpactSource> */
    public function all(): array
    {
        return $this->sources;
    }
}
