<?php

namespace Platform\People\Support;

use Illuminate\Support\Carbon;

/**
 * Leitet die organisatorische Position einer Person aus dem Org-Graphen ab
 * (Richtung People -> Organization, guarded gegen fehlendes Modul).
 *
 * Gestufte Signalstärke:
 *   Abteilung: Rollen-Kontext (RoleAssignment.context_entity) > Relations
 *              (is_part_of/team) > reports_to > Baum-Parent
 *   Carrier:   Relation works_for > Carrier-Vorfahr der Abteilung > Baum-Carrier
 *
 * Rückgabe: carrier, department (je {id,name} oder null), source (Herkunft der
 * Abteilung), path (Baum-Pfad, Baseline).
 */
class OrgContextResolver
{
    private string $entityModel = \Platform\Organization\Models\OrganizationEntity::class;
    private string $roleAssignmentModel = \Platform\Organization\Models\OrganizationRoleAssignment::class;
    private string $relationshipModel = \Platform\Organization\Models\OrganizationEntityRelationship::class;
    private string $relationTypeModel = \Platform\Organization\Models\OrganizationEntityRelationType::class;

    /**
     * @return array{carrier: ?array{id:int,name:string}, department: ?array{id:int,name:string}, source: ?string, path: array<int,string>}
     */
    public function resolve(?int $orgEntityId): array
    {
        $empty = ['carrier' => null, 'department' => null, 'source' => null, 'path' => []];
        if (! $orgEntityId || ! class_exists($this->entityModel)) {
            return $empty;
        }

        try {
            // ── Baseline: Baum-Parent-Kette ───────────────────────────────
            [$parentCarrier, $parentDept, $path] = $this->fromParentChain($orgEntityId);

            // ── Signal: Rollen-Kontext (stärkste Abteilungsangabe) ────────
            $roleContextIds = $this->validRoleContextIds($orgEntityId);

            // ── Signal: Relations (nach Typ) ──────────────────────────────
            $rel = $this->relationTargetsByCode($orgEntityId); // ['works_for'=>[ids], 'is_part_of'=>[...], ...]

            // ── Abteilung nach Stärke wählen (erste Nicht-Carrier-Einheit) ─
            $department = null; $source = null;
            foreach ([
                ['ids' => $roleContextIds,                 'label' => 'Rolle'],
                ['ids' => array_merge($rel['is_part_of'] ?? [], $rel['team'] ?? []), 'label' => 'Relation (Mitglied)'],
                ['ids' => $rel['reports_to'] ?? [],        'label' => 'Relation (berichtet an)'],
            ] as $cand) {
                foreach ($cand['ids'] as $id) {
                    $node = $this->entity($id);
                    if ($node && ! $this->isCarrier($node)) {
                        $department = ['id' => (int) $node->id, 'name' => (string) $node->name];
                        $source = $cand['label'];
                        break 2;
                    }
                }
            }
            if (! $department && $parentDept) {
                $department = $parentDept;
                $source = 'Baum';
            }

            // ── Carrier nach Stärke wählen ────────────────────────────────
            $carrier = null;
            foreach ($rel['works_for'] ?? [] as $id) {          // 1) expliziter works_for
                $node = $this->entity($id);
                if ($node && $this->isCarrier($node)) {
                    $carrier = ['id' => (int) $node->id, 'name' => (string) $node->name];
                    break;
                }
            }
            if (! $carrier && $department) {                    // 2) Carrier-Vorfahr der Abteilung
                $carrier = $this->carrierAncestorOf($department['id']);
            }
            if (! $carrier) {                                   // 3) Baum-Carrier
                $carrier = $parentCarrier;
            }
            if (! $carrier) {                                   // 4) Carrier unter Rollen-/Relation-Signalen
                foreach (array_merge($roleContextIds, $rel['works_for'] ?? [], $rel['is_part_of'] ?? []) as $id) {
                    $node = $this->entity($id);
                    if ($node && $this->isCarrier($node)) {
                        $carrier = ['id' => (int) $node->id, 'name' => (string) $node->name];
                        break;
                    }
                }
            }

            return ['carrier' => $carrier, 'department' => $department, 'source' => $source, 'path' => $path];
        } catch (\Throwable $e) {
            return $empty;
        }
    }

    // ── Signale ───────────────────────────────────────────────────────────

    /** @return array<int,int> */
    private function validRoleContextIds(int $personEntityId): array
    {
        if (! class_exists($this->roleAssignmentModel)) {
            return [];
        }
        $today = Carbon::today()->toDateString();

        return $this->roleAssignmentModel::query()
            ->where('person_entity_id', $personEntityId)
            ->where(fn ($q) => $q->whereNull('valid_from')->orWhereDate('valid_from', '<=', $today))
            ->where(fn ($q) => $q->whereNull('valid_to')->orWhereDate('valid_to', '>=', $today))
            ->orderByDesc('valid_from')
            ->pluck('context_entity_id')
            ->filter()->map(fn ($v) => (int) $v)->unique()->values()->all();
    }

    /**
     * Ziel-Entities gültiger Relationen der Person, gruppiert nach Relation-Code.
     * @return array<string, array<int,int>>
     */
    private function relationTargetsByCode(int $personEntityId): array
    {
        $out = ['works_for' => [], 'is_part_of' => [], 'team' => [], 'reports_to' => []];
        if (! class_exists($this->relationshipModel) || ! class_exists($this->relationTypeModel)) {
            return $out;
        }

        $typeIdToCode = $this->relationTypeModel::query()
            ->whereIn('code', array_keys($out))
            ->pluck('code', 'id'); // [id => code]

        if ($typeIdToCode->isEmpty()) {
            return $out;
        }

        $today = Carbon::today()->toDateString();

        $rels = $this->relationshipModel::query()
            ->where('from_entity_id', $personEntityId)
            ->whereIn('relation_type_id', $typeIdToCode->keys())
            ->where(fn ($q) => $q->whereNull('valid_from')->orWhereDate('valid_from', '<=', $today))
            ->where(fn ($q) => $q->whereNull('valid_to')->orWhereDate('valid_to', '>=', $today))
            ->get(['to_entity_id', 'relation_type_id']);

        foreach ($rels as $r) {
            $code = $typeIdToCode[$r->relation_type_id] ?? null;
            if ($code && $r->to_entity_id) {
                $out[$code][] = (int) $r->to_entity_id;
            }
        }

        return $out;
    }

    // ── Baum ───────────────────────────────────────────────────────────────

    /** @return array{0: ?array{id:int,name:string}, 1: ?array{id:int,name:string}, 2: array<int,string>} */
    private function fromParentChain(int $orgEntityId): array
    {
        $carrier = null; $department = null; $path = [];
        $current = $this->entity($orgEntityId);
        $node = $current?->parent_entity_id ? $this->entity($current->parent_entity_id) : null;
        $seen = []; $depth = 0;

        while ($node && $depth < 20 && ! in_array($node->id, $seen, true)) {
            $seen[] = $node->id;
            $path[] = $node->name;
            if ($this->isCarrier($node) && ! $carrier) {
                $carrier = ['id' => (int) $node->id, 'name' => (string) $node->name];
            }
            if (! $this->isCarrier($node) && ! $department) {
                $department = ['id' => (int) $node->id, 'name' => (string) $node->name];
            }
            $node = $node->parent_entity_id ? $this->entity($node->parent_entity_id) : null;
            $depth++;
        }

        return [$carrier, $department, $path];
    }

    private function carrierAncestorOf(int $entityId): ?array
    {
        $node = $this->entity($entityId);
        $seen = []; $depth = 0;
        while ($node && $depth < 20 && ! in_array($node->id, $seen, true)) {
            if ($this->isCarrier($node)) {
                return ['id' => (int) $node->id, 'name' => (string) $node->name];
            }
            $seen[] = $node->id;
            $node = $node->parent_entity_id ? $this->entity($node->parent_entity_id) : null;
            $depth++;
        }
        return null;
    }

    // ── Helpers ─────────────────────────────────────────────────────────────

    private array $cache = [];

    private function entity(int $id)
    {
        if (array_key_exists($id, $this->cache)) {
            return $this->cache[$id];
        }
        return $this->cache[$id] = $this->entityModel::with('type')->find($id);
    }

    private function isCarrier($node): bool
    {
        return ($node->type?->vsm_class) === 'carrier';
    }
}
