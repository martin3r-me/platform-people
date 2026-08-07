<?php

namespace Platform\People\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Symfony\Component\Uid\UuidV7;

/**
 * Zuweisung Employee ↔ JobProfile mit Auslastung (aus Organization, Phase 2b).
 * Ersetzt PersonJobProfile: person_entity -> employee.
 *
 * roleOverrides() referenziert OrganizationRole (weicher Pivot) — Rollen bleiben
 * in Organization.
 */
class EmployeeJobProfile extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'people_employee_job_profiles';

    protected $fillable = [
        'uuid',
        'team_id',
        'employee_id',
        'job_profile_id',
        'context_entity_id',
        'percentage',
        'is_primary',
        'valid_from',
        'valid_to',
        'note',
    ];

    protected $casts = [
        'percentage' => 'integer',
        'is_primary' => 'boolean',
        'valid_from' => 'date',
        'valid_to'   => 'date',
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

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function jobProfile(): BelongsTo
    {
        return $this->belongsTo(JobProfile::class, 'job_profile_id');
    }

    /**
     * Kontext-Knoten im Org-Graph (weiche Referenz auf organization_entities).
     */
    public function contextEntity(): BelongsTo
    {
        return $this->belongsTo(\Platform\Organization\Models\OrganizationEntity::class, 'context_entity_id');
    }

    /**
     * Override-Rollen: individuelle Anteile pro Zuweisung. Wenn leer, gelten die
     * Default-Anteile aus dem JobProfile.
     */
    public function roleOverrides(): BelongsToMany
    {
        return $this->belongsToMany(\Platform\Organization\Models\OrganizationRole::class, 'people_employee_job_profile_roles', 'employee_job_profile_id', 'role_id')
            ->withPivot('percentage_share', 'sort_order')
            ->withTimestamps()
            ->orderBy('people_employee_job_profile_roles.sort_order');
    }

    /**
     * Effektive Rollen-Verteilung: Overrides schlagen JobProfile-Defaults.
     * Rückgabe: Collection von ['role_id', 'role', 'percentage_share', 'source'].
     */
    public function effectiveRoleShares(): Collection
    {
        $this->loadMissing(['roleOverrides', 'jobProfile.roles']);

        if ($this->roleOverrides->isNotEmpty()) {
            return $this->roleOverrides->map(fn ($r) => [
                'role_id'          => $r->id,
                'role'             => $r,
                'percentage_share' => (int) $r->pivot->percentage_share,
                'source'           => 'override',
            ]);
        }

        $jp = $this->jobProfile;
        if (! $jp) {
            return collect();
        }

        return $jp->roles->map(fn ($r) => [
            'role_id'          => $r->id,
            'role'             => $r,
            'percentage_share' => (int) $r->pivot->percentage_share,
            'source'           => 'default',
        ]);
    }

    public function scopeForTeam($query, $teamId)
    {
        return $query->where('team_id', $teamId);
    }
}
