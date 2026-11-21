<?php

namespace Platform\People\Models;

use Illuminate\Database\Eloquent\Model;

/** Wirkungs-Score eines Mitarbeiters an einem Tag (Verlauf der Wirkung). */
class ImpactSnapshot extends Model
{
    protected $table = 'people_impact_snapshots';

    protected $fillable = ['team_id', 'employee_id', 'taken_on', 'score', 'parts', 'indicators'];

    protected $casts = [
        'taken_on' => 'date',
        'score' => 'float',
        'parts' => 'array',
        'indicators' => 'array',
    ];
}
