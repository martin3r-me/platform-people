<?php

namespace Platform\People\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Eine Regel der Arbeitsweise eines Agenten (gespiegelt). Nur Lesesicht — geändert wird per Kuratier-Auftrag.
 */
class AgentRule extends Model
{
    protected $table = 'people_agent_rules';

    protected $fillable = [
        'org_entity_id', 'rule_id', 'ord', 'kind', 'trigger', 'text', 'why', 'source', 'status',
        'supersedes', 'confirmed', 'refuted', 'sha', 'rule_created_at', 'rule_updated_at', 'last_used_at',
    ];

    protected $casts = [
        'rule_id' => 'integer',
        'ord' => 'integer',
        'supersedes' => 'integer',
        'confirmed' => 'integer',
        'refuted' => 'integer',
    ];

    public const LIVE = ['active', 'probation'];

    public function isLive(): bool
    {
        return in_array($this->status, self::LIVE, true);
    }
}
