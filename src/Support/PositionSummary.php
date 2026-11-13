<?php

namespace Platform\People\Support;

use Platform\People\Models\EmployeeJobProfile;
use Platform\People\Models\JobProfile;

/**
 * Hauptstelle und deren Bereich (Einheit in der Organisation) je Mitarbeiter — für Listen, Seitenköpfe und
 * Navigation. Hauptstelle = gültige Zuweisung, Hauptprofil zuerst, dann Anteil (wie HeldJobProfile).
 */
class PositionSummary
{
    /** @return array<int, array{position_id:int, position:string, unit_id:?int, unit:?string}> [employee_id => …] */
    public static function forEmployees(array $employeeIds): array
    {
        $employeeIds = array_values(array_unique(array_filter(array_map('intval', $employeeIds))));
        if (! $employeeIds) {
            return [];
        }
        $today = now()->toDateString();
        $primary = [];
        EmployeeJobProfile::query()->whereIn('employee_id', $employeeIds)
            ->where(fn ($q) => $q->whereNull('valid_from')->orWhere('valid_from', '<=', $today))
            ->where(fn ($q) => $q->whereNull('valid_to')->orWhere('valid_to', '>=', $today))
            ->whereHas('jobProfile', fn ($q) => $q->where('status', 'active'))
            ->with('jobProfile:id,name')
            ->orderByDesc('is_primary')->orderByDesc('percentage')->orderByDesc('id')
            ->get()
            ->each(function (EmployeeJobProfile $a) use (&$primary) {
                $primary[$a->employee_id] ??= $a->jobProfile;
            });

        $units = self::unitsOf(array_map(fn (JobProfile $p) => (int) $p->id, array_values($primary)));
        $out = [];
        foreach ($primary as $employeeId => $profile) {
            $unit = $units[(int) $profile->id] ?? null;
            $out[$employeeId] = [
                'position_id' => (int) $profile->id,
                'position' => $profile->name,
                'unit_id' => $unit['id'] ?? null,
                'unit' => $unit['name'] ?? null,
            ];
        }

        return $out;
    }

    /** @return array<int, array{id:int, name:string}> [job_profile_id => Einheit] */
    public static function unitsOf(array $jobProfileIds): array
    {
        $map = OrganizationLink::entitiesOf('people_job_profile', $jobProfileIds);
        $names = self::entityNames(array_values($map));
        $out = [];
        foreach ($map as $profileId => $entityId) {
            if (isset($names[$entityId])) {
                $out[(int) $profileId] = ['id' => (int) $entityId, 'name' => $names[$entityId]];
            }
        }

        return $out;
    }

    /** @return array<int,string> */
    public static function entityNames(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if (! $ids) {
            return [];
        }
        try {
            return \Platform\Organization\Models\OrganizationEntity::query()->whereIn('id', $ids)->pluck('name', 'id')->all();
        } catch (\Throwable $e) {
            return [];
        }
    }
}
