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
 *
 * Übergang: Profile, die noch per owner_entity_id an einem Knoten hängen, werden als Rückfall gefunden.
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

        $missing = array_diff($entityIds, array_keys($out));
        if ($missing) {
            JobProfile::query()->whereIn('owner_entity_id', $missing)->where('status', 'active')->orderByDesc('id')->get()
                ->each(function (JobProfile $jp) use (&$out) {
                    $out[(int) $jp->owner_entity_id] ??= $jp;
                });
        }

        return $out;
    }
}
