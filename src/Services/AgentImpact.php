<?php

namespace Platform\People\Services;

use Platform\People\Models\Employee;

/**
 * Die Wirkung eines Agenten für die Agent-Schnittstelle der Organisation (Contracts\AgentImpactProvider):
 * derselbe ImpactScore wie auf der Mitarbeiter-Seite. Gebunden im ServiceProvider, nur wenn organization
 * den Vertrag mitbringt.
 */
class AgentImpact implements \Platform\Organization\Contracts\AgentImpactProvider
{
    public function impactFor(int $entityId): ?array
    {
        $employee = Employee::where('org_entity_id', $entityId)->where('status', 'active')->first();
        if (! $employee) {
            return null;
        }

        return app(ImpactScore::class)->forEmployee($employee);
    }
}
