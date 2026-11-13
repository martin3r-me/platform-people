<?php

namespace Platform\People\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Ein Kuratier-Auftrag an einen Agenten: eine Gedächtnis-Datei oder Regel ändern, stilllegen, ergänzen,
 * freigeben. Entwürfe (draft) warten auf Freigabe; offene Aufträge (pending) holt das Engram ab, wendet sie
 * nur auf dem Stand base_sha an (sonst „stale“) und quittiert.
 */
class AgentCuration extends Model
{
    protected $table = 'people_agent_curations';

    protected $fillable = [
        'org_entity_id', 'path', 'op', 'base_sha', 'content', 'note', 'status', 'result', 'created_by_user_id', 'applied_at',
    ];

    protected $casts = [
        'applied_at' => 'datetime',
    ];

    public const OPS = ['replace', 'retire', 'revise', 'add', 'approve', 'approve_import', 'reject_import'];

    public const FINAL = ['applied', 'stale', 'rejected', 'cancelled'];

    /** Korrektur = ein Mensch ändert oder streicht etwas am Gedächtnis; Freigaben sind keine. */
    public const CORRECTION_OPS = ['replace', 'retire', 'revise'];
}
