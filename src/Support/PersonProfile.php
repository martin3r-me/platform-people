<?php

namespace Platform\People\Support;

use Illuminate\Database\Eloquent\Relations\Relation;
use Platform\Organization\Services\EntityDimensionBridge;
use Platform\Organization\Services\EntityLinkRegistry;

/**
 * PersonProfile — löst den am Personen-Knoten hängenden CRM-Kontakt (crm_contact) GRAPH-NATIV
 * auf und liefert dessen Anzeige-Metadaten. people kennt CRMs Model NICHT — es fragt die
 * organization (Backbone) und rendert, was der crm_contact-Provider zurückgibt. Graceful:
 * kein CRM / kein Link → null. 1:1 vom customer CompanyProfile::crmForEntity-Muster.
 */
class PersonProfile
{
    /** @return array<string,mixed>|null CRM-Stammdaten des verknüpften crm_contact, sonst null. */
    public static function crmForEntity(?int $entityId): ?array
    {
        if (!$entityId) {
            return null;
        }

        try {
            $links = EntityDimensionBridge::linksForEntities([$entityId]);
            $reverse = array_flip(Relation::morphMap());

            $contactIds = [];
            foreach ($links as $link) {
                $alias = $reverse[$link->linkable_type] ?? $link->linkable_type;
                if ($alias === 'crm_contact') {
                    $contactIds[] = $link->linkable_id;
                }
            }
            if (empty($contactIds)) {
                return null;
            }

            $fqcn = Relation::getMorphedModel('crm_contact');
            if (!$fqcn || !class_exists($fqcn)) {
                return null; // CRM nicht installiert
            }

            $provider = resolve(EntityLinkRegistry::class)->getProvider('crm_contact');
            if (!$provider) {
                return null;
            }

            $query = $fqcn::whereIn('id', array_unique($contactIds));
            $provider->applyEagerLoading($query, 'crm_contact', $fqcn);
            $model = $query->first();
            if (!$model) {
                return null;
            }

            $meta = $provider->extractMetadata('crm_contact', $model);

            return !empty($meta) ? $meta : null;
        } catch (\Throwable $e) {
            // organization/CRM nicht verfügbar oder Schema-Drift — nur Basis zeigen.
            return null;
        }
    }
}
