<?php

namespace Platform\People\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Eine steuernde Datei aus dem Vault eines Agenten (Arbeitsweise, Index, offene Fragen, Notiz),
 * vom Engram gespiegelt. Nur Lesesicht — geändert wird über einen Kuratier-Auftrag.
 */
class AgentMemoryDoc extends Model
{
    protected $table = 'people_agent_memory_docs';

    protected $fillable = ['org_entity_id', 'path', 'kind', 'title', 'sha', 'content', 'file_modified_at'];

    protected $casts = [
        'file_modified_at' => 'datetime',
    ];

    public const KINDS = ['routine', 'index', 'questions', 'playbook'];
}
