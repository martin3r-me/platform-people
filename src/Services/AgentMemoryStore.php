<?php

namespace Platform\People\Services;

use Illuminate\Support\Carbon;
use Platform\People\Models\AgentCuration;
use Platform\People\Models\AgentMemoryDoc;
use Platform\People\Models\AgentRule;
use Platform\People\Models\Employee;

/**
 * Das Gedächtnis der Agenten in people: die Organisation reicht über ihre Agent-Schnittstelle weiter
 * (Contracts\AgentMemoryStore), hier wird gespiegelt, gezeigt und kuratiert. Gebunden im ServiceProvider,
 * nur wenn organization den Vertrag mitbringt.
 */
class AgentMemoryStore implements \Platform\Organization\Contracts\AgentMemoryStore
{
    public function storeDocs(int $entityId, array $docs): array
    {
        $existing = AgentMemoryDoc::where('org_entity_id', $entityId)->get()->keyBy('path');
        $seen = [];
        foreach ($docs as $d) {
            $seen[$d['path']] = true;
            $row = $existing[$d['path']] ?? new AgentMemoryDoc(['org_entity_id' => $entityId, 'path' => $d['path']]);
            if ($row->exists && $row->sha === $d['sha']) {
                continue;
            }
            $row->fill([
                'kind' => $d['kind'],
                'title' => $d['title'] ?? null,
                'sha' => $d['sha'],
                'content' => (string) ($d['content'] ?? ''),
                'file_modified_at' => isset($d['modified_at']) ? Carbon::createFromTimestamp($d['modified_at']) : null,
            ])->save();
        }
        $gone = $existing->keys()->reject(fn ($p) => isset($seen[$p]))->all();
        if ($gone) {
            AgentMemoryDoc::where('org_entity_id', $entityId)->whereIn('path', $gone)->delete();
        }

        return ['docs' => count($seen), 'removed' => count($gone)];
    }

    public function storeRules(int $entityId, array $rules): array
    {
        $existing = AgentRule::where('org_entity_id', $entityId)->get()->keyBy('rule_id');
        $seen = [];
        foreach ($rules as $r) {
            $seen[(int) $r['id']] = true;
            $row = $existing[(int) $r['id']] ?? new AgentRule(['org_entity_id' => $entityId, 'rule_id' => (int) $r['id']]);
            $row->fill([
                'ord' => (int) ($r['ord'] ?? 0),
                'kind' => $r['kind'],
                'trigger' => (string) ($r['trigger'] ?? ''),
                'text' => $r['text'],
                'why' => $r['why'] ?? null,
                'source' => $r['source'] ?? 'learned',
                'status' => $r['status'],
                'supersedes' => (int) ($r['supersedes'] ?? 0),
                'confirmed' => (int) ($r['confirmed'] ?? 0),
                'refuted' => (int) ($r['refuted'] ?? 0),
                'sha' => $r['sha'],
                'rule_created_at' => $r['created_at'] ?? null,
                'rule_updated_at' => $r['updated_at'] ?? null,
                'last_used_at' => $r['last_used_at'] ?? null,
            ]);
            if (! $row->exists || $row->isDirty()) {
                $row->save();
            }
        }
        $gone = $existing->keys()->reject(fn ($id) => isset($seen[$id]))->all();
        if ($gone) {
            AgentRule::where('org_entity_id', $entityId)->whereIn('rule_id', $gone)->delete();
        }

        return ['rules' => count($seen), 'removed' => count($gone)];
    }

    public function pendingCurations(int $entityId, int $limit = 20): array
    {
        return AgentCuration::where('org_entity_id', $entityId)
            ->where('status', 'pending')->orderBy('id')->limit($limit)->get()
            ->map(fn (AgentCuration $c) => [
                'id' => $c->id,
                'path' => $c->path,
                'op' => $c->op,
                'base_sha' => $c->base_sha,
                'content' => $c->content,
                'note' => $c->note,
            ])->values()->all();
    }

    public function acknowledgeCuration(int $entityId, int $curationId, string $status, ?string $result): ?string
    {
        $c = AgentCuration::where('org_entity_id', $entityId)->find($curationId);
        if (! $c) {
            return null;
        }
        if ($c->status === 'pending') {
            $c->status = $status;
            $c->result = $result;
            $c->applied_at = now();
            $c->save();
        }

        return $c->status;
    }

    public function correctionCounts(array $entityIds, Carbon $from, Carbon $to): array
    {
        if ($entityIds === []) {
            return [];
        }

        return AgentCuration::whereIn('org_entity_id', $entityIds)
            ->whereIn('op', AgentCuration::CORRECTION_OPS)->where('status', 'applied')
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw('org_entity_id as k, count(*) as n')->groupBy('org_entity_id')
            ->pluck('n', 'k')->map(fn ($n) => (int) $n)->all();
    }

    public function memoryUrl(int $entityId): ?string
    {
        $employee = Employee::where('org_entity_id', $entityId)->first();

        return $employee ? route('people.employees.show', ['employee' => $employee->id, 'reiter' => 'gedaechtnis']) : null;
    }
}
