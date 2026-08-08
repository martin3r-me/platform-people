<?php

namespace Platform\People\Support;

/**
 * Leitet die organisatorische Position einer Person aus dem Org-Graphen ab
 * (Richtung People -> Organization, guarded gegen fehlendes Modul).
 *
 * v1: Baseline über die parent_entity_id-Kette — nächster Carrier-Vorfahr =
 * Arbeitgeber-Kandidat, nächster Nicht-Person-/Nicht-Carrier-Vorfahr =
 * Abteilung. Rollen-Kontext + Relations verstärken das später (Schritt 2).
 */
class OrgContextResolver
{
    /**
     * @return array{carrier: ?array{id:int,name:string}, department: ?array{id:int,name:string}, path: array<int,string>}
     */
    public function resolve(?int $orgEntityId): array
    {
        $empty = ['carrier' => null, 'department' => null, 'path' => []];
        if (! $orgEntityId) {
            return $empty;
        }

        try {
            $model = \Platform\Organization\Models\OrganizationEntity::class;
            if (! class_exists($model)) {
                return $empty;
            }

            $carrier = null;
            $department = null;
            $path = [];

            $current = $model::with('type')->find($orgEntityId);
            $seen = [];
            $depth = 0;

            // Vom Personen-Knoten aufwärts durch parent_entity_id.
            $node = $current?->parent_entity_id
                ? $model::with('type')->find($current->parent_entity_id)
                : null;

            while ($node && $depth < 20 && ! in_array($node->id, $seen, true)) {
                $seen[] = $node->id;
                $path[] = $node->name;
                $vsm = $node->type?->vsm_class;

                if ($vsm === 'carrier' && ! $carrier) {
                    $carrier = ['id' => (int) $node->id, 'name' => (string) $node->name];
                }
                // Erste Nicht-Carrier-Einheit oberhalb der Person = Abteilung.
                if ($vsm !== 'carrier' && ! $department) {
                    $department = ['id' => (int) $node->id, 'name' => (string) $node->name];
                }

                $node = $node->parent_entity_id
                    ? $model::with('type')->find($node->parent_entity_id)
                    : null;
                $depth++;
            }

            return ['carrier' => $carrier, 'department' => $department, 'path' => $path];
        } catch (\Throwable $e) {
            return $empty;
        }
    }
}
