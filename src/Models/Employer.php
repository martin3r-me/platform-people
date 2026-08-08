<?php

namespace Platform\People\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Symfony\Component\Uid\UuidV7;

/**
 * Employer — HR-Overlay auf einen Org-Carrier-Knoten. Verwaltet den Arbeitgeber
 * (mehrere Carrier möglich) samt Vertrags-Defaults. org_entity_id ist eine
 * weiche Referenz auf organization_entities (Carrier).
 */
class Employer extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'people_employers';

    protected $fillable = [
        'uuid',
        'team_id',
        'org_entity_id',
        'name',
        'is_active',
        'default_vacation_days',
        'default_weekly_hours',
        'working_time_model',
        'note',
    ];

    protected $casts = [
        'is_active'             => 'boolean',
        'default_vacation_days' => 'integer',
        'default_weekly_hours'  => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model) {
            if (empty($model->uuid)) {
                do {
                    $uuid = UuidV7::generate();
                } while (self::where('uuid', $uuid)->exists());
                $model->uuid = $uuid;
            }
        });
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(\Platform\Core\Models\Team::class);
    }

    /**
     * Der Org-Carrier-Knoten hinter diesem Arbeitgeber (weiche Referenz).
     */
    public function organizationEntity(): BelongsTo
    {
        return $this->belongsTo(\Platform\Organization\Models\OrganizationEntity::class, 'org_entity_id');
    }

    public function employments(): HasMany
    {
        return $this->hasMany(Employment::class, 'employer_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForTeam($query, $teamId)
    {
        return $query->where('team_id', $teamId);
    }
}
