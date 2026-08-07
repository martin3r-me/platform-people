<?php

namespace Platform\People\Support;

use Platform\Organization\Services\DimensionLinkService;
use Platform\Organization\Models\OrganizationDimensionDefinition;
use Platform\Organization\Models\OrganizationDimensionValue;
use Platform\Organization\Models\OrganizationDimensionLink;

/**
 * OrganizationLink — hält den dimension_link eines people-Objekts auf seinen Org-Personen-
 * Knoten synchron (genau EIN Knoten je Objekt). Setzt/ändert/entfernt den Link in der
 * "entity"-Dimension. Fehlertolerant (blockiert Saves nicht, falls organization fehlt).
 *
 * 1:1 vom occupational/customer-Muster — der Mensch (Employee) hängt graph-nativ am
 * Org-Personen-Knoten, so wie später auch die practice-Arzt-Facette am selben Knoten.
 */
class OrganizationLink
{
    public static function sync(string $contextAlias, int $contextId, ?int $entityId, ?int $teamId, ?int $userId = null): void
    {
        try {
            $def = OrganizationDimensionDefinition::findByKey('entity');
            if (!$def) {
                return;
            }

            $resolvedType = DimensionLinkService::resolveContextType($contextAlias);

            // Bestehende entity-Links dieses Kontexts entfernen (Ein-Knoten-Semantik).
            OrganizationDimensionLink::query()
                ->where('dimension_definition_id', $def->id)
                ->where('linkable_type', $resolvedType)
                ->where('linkable_id', $contextId)
                ->delete();

            if (!$entityId) {
                return;
            }

            $dimValue = OrganizationDimensionValue::query()
                ->where('dimension_definition_id', $def->id)
                ->where('metadata->source_entity_id', $entityId)
                ->first();
            if (!$dimValue) {
                return;
            }

            (new DimensionLinkService())->link('entity', $contextAlias, $contextId, $dimValue->id, [
                'is_primary'         => true,
                'team_id'            => $teamId,
                'created_by_user_id' => $userId,
            ]);
        } catch (\Throwable $e) {
            // organization nicht verfügbar / Sync-Fehler — Save nicht blockieren.
        }
    }
}
