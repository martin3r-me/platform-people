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

    /** Der Org-Knoten, an dem ein people-Objekt hängt (null = nicht verknüpft / organization fehlt). */
    public static function entityOf(string $contextAlias, int $contextId): ?int
    {
        return self::entitiesOf($contextAlias, [$contextId])[$contextId] ?? null;
    }

    /** @return array<int,int> [context_id => entity_id] */
    public static function entitiesOf(string $contextAlias, array $contextIds): array
    {
        $contextIds = array_values(array_unique(array_filter(array_map('intval', $contextIds))));
        if (! $contextIds) {
            return [];
        }
        try {
            $def = OrganizationDimensionDefinition::findByKey('entity');
            if (! $def) {
                return [];
            }
            $links = OrganizationDimensionLink::query()
                ->where('dimension_definition_id', $def->id)
                ->where('linkable_type', DimensionLinkService::resolveContextType($contextAlias))
                ->whereIn('linkable_id', $contextIds)
                ->get(['linkable_id', 'dimension_value_id']);
            $sources = OrganizationDimensionValue::query()->whereIn('id', $links->pluck('dimension_value_id'))->get()
                ->mapWithKeys(fn ($v) => [(int) $v->id => (int) ($v->metadata['source_entity_id'] ?? 0)]);
            $out = [];
            foreach ($links as $l) {
                $entityId = $sources[(int) $l->dimension_value_id] ?? 0;
                if ($entityId > 0) {
                    $out[(int) $l->linkable_id] = $entityId;
                }
            }

            return $out;
        } catch (\Throwable $e) {
            return [];
        }
    }
}
