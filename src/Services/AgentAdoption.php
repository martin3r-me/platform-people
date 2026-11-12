<?php

namespace Platform\People\Services;

use Platform\People\Models\Employee;
use Platform\People\Models\Employment;

/**
 * Agenten (Engrams) der Organisation als Mitarbeiter führen: je Agent-Knoten ohne Mitarbeiter-Satz
 * ein Employee (kind=agent, verknüpfter User + Knoten) und eine Anstellung ab seiner Geburt — so laufen
 * Stelle, Skills und Entwicklung für Agenten über dieselben Wege wie für Menschen.
 * Guarded: ohne organization-Modul gibt es nichts zu übernehmen.
 */
class AgentAdoption
{
    /** Agent-Knoten des Teams ohne Mitarbeiter-Satz (auch gelöschte Sätze zählen als bekannt). */
    public function pending(int $teamId): array
    {
        try {
            $known = Employee::withTrashed()->forTeam($teamId)->whereNotNull('org_entity_id')->pluck('org_entity_id')->all();

            return \Platform\Organization\Models\OrganizationEntity::query()
                ->forTeam($teamId)->agents()->whereNotIn('id', $known)->orderBy('name')->get()->all();
        } catch (\Throwable $e) {
            return [];
        }
    }

    /** @return list<Employee> die neu angelegten Mitarbeiter */
    public function adopt(int $teamId): array
    {
        $created = [];
        foreach ($this->pending($teamId) as $agent) {
            $employee = new Employee();
            $employee->fill([
                'display_name'  => (string) $agent->name,
                'kind'          => 'agent',
                'status'        => $agent->is_active ? 'active' : 'inactive',
                'org_entity_id' => (int) $agent->id,
                'user_id'       => $agent->linked_user_id ? (int) $agent->linked_user_id : null,
            ]);
            $employee->team_id = $teamId;
            $employee->save();

            Employment::create([
                'team_id'         => $teamId,
                'employee_id'     => $employee->id,
                'employment_type' => 'agent',
                'status'          => 'active',
                'started_on'      => $agent->created_at?->toDateString(),
            ]);
            $created[] = $employee;
        }

        return $created;
    }
}
