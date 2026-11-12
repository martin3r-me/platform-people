<?php

namespace Platform\People\Support;

use Platform\People\Models\Employee;
use Platform\People\Models\EmployeeJobProfile;
use Platform\People\Models\JobProfile;

/**
 * Welche Stelle trägt ein Org-Knoten? Die Stelle hängt am Mitarbeiter (EmployeeJobProfile), nicht am Knoten:
 * Knoten → Mitarbeiter (org_entity_id) → gültige Zuweisung (Hauptprofil zuerst, dann Anteil) → aktives Profil.
 * Für Menschen und Agenten gleich. Einstiegspunkt für andere Module (organization fragt hier, statt
 * people-Tabellen selbst zu lesen).
 */
class HeldJobProfile
{
    public static function forEntity(int $entityId): ?JobProfile
    {
        return $entityId > 0 ? (self::forEntities([$entityId])[$entityId] ?? null) : null;
    }

    /** @return array<int, JobProfile> [entity_id => Stelle] */
    public static function forEntities(array $entityIds): array
    {
        $entityIds = array_values(array_unique(array_filter(array_map('intval', $entityIds))));
        if (! $entityIds) {
            return [];
        }
        $today = now()->toDateString();
        $out = [];

        $employees = Employee::query()->whereIn('org_entity_id', $entityIds)->pluck('org_entity_id', 'id');
        if ($employees->isNotEmpty()) {
            $assignments = EmployeeJobProfile::query()
                ->whereIn('employee_id', $employees->keys())
                ->where(fn ($q) => $q->whereNull('valid_from')->orWhere('valid_from', '<=', $today))
                ->where(fn ($q) => $q->whereNull('valid_to')->orWhere('valid_to', '>=', $today))
                ->whereHas('jobProfile', fn ($q) => $q->where('status', 'active'))
                ->with('jobProfile')
                ->orderByDesc('is_primary')->orderByDesc('percentage')->orderByDesc('id')
                ->get();
            foreach ($assignments as $a) {
                $entityId = (int) $employees[$a->employee_id];
                $out[$entityId] ??= $a->jobProfile;
            }
        }


        return $out;
    }
}
