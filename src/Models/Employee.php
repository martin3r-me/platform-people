<?php

namespace Platform\People\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Symfony\Component\Uid\UuidV7;

/**
 * Employee — Stammsatz des angestellten Menschen.
 *
 * Join-Anker zwischen den Projektionen einer Person: `user` (Platform-User) und
 * `org_entity_id` (Organization-Person-Entity, weiche Referenz ohne FK).
 */
class Employee extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'people_employees';

    protected $fillable = [
        'uuid',
        'team_id',
        'user_id',
        'org_entity_id',
        'display_name',
        'employee_number',
        'status',
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

        // org_entity_id (Person-Knoten) graph-nativ auf einen dimension_link spiegeln.
        static::saved(function (self $model) {
            if ($model->wasRecentlyCreated || $model->wasChanged('org_entity_id')) {
                \Platform\People\Support\OrganizationLink::sync(
                    'people_employee',
                    (int) $model->id,
                    $model->org_entity_id ? (int) $model->org_entity_id : null,
                    $model->team_id ? (int) $model->team_id : null,
                    auth()->id(),
                );
            }
        });

        // Beim Löschen die Facette (dimension_link) vom Knoten entfernen — kein Phantom.
        static::deleted(function (self $model) {
            \Platform\People\Support\OrganizationLink::sync(
                'people_employee',
                (int) $model->id,
                null,
                $model->team_id ? (int) $model->team_id : null,
                auth()->id(),
            );
        });
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(\Platform\Core\Models\Team::class);
    }

    public function organizationEntity(): BelongsTo
    {
        return $this->belongsTo(\Platform\Organization\Models\OrganizationEntity::class, 'org_entity_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(\Platform\Core\Models\User::class);
    }

    public function employments(): HasMany
    {
        return $this->hasMany(Employment::class, 'employee_id');
    }

    public function skills(): HasMany
    {
        return $this->hasMany(EmployeeSkill::class, 'employee_id');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeForTeam($query, $teamId)
    {
        return $query->where('team_id', $teamId);
    }
}
