<?php

namespace Platform\People\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Symfony\Component\Uid\UuidV7;

/**
 * JobProfile — wiederverwendbare Stellenbeschreibung (aus Organization, Phase 2b).
 *
 * Rollen bleiben in Organization: roles() referenziert OrganizationRole über
 * den weichen Pivot people_job_profile_roles (Abhängigkeit People -> Organization).
 * Skills leben in People: skillRecords() zeigt auf den people_skills-Katalog.
 */
class JobProfile extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'people_job_profiles';

    protected $fillable = [
        'uuid',
        'team_id',
        'user_id',
        'name',
        'description',
        'purpose',
        'job_family',
        'content',
        'level',
        'vsm_level',
        'skills',
        'responsibilities',
        'requirements',
        'soft_skills',
        'kpis',
        'exclusion_criteria',
        'work_model',
        'reporting',
        'status',
        'effective_from',
        'effective_to',
    ];

    protected $casts = [
        'vsm_level'          => 'array',
        'skills'             => 'array',
        'responsibilities'   => 'array',
        'requirements'       => 'array',
        'soft_skills'        => 'array',
        'kpis'               => 'array',
        'exclusion_criteria' => 'array',
        'work_model'         => 'array',
        'reporting'          => 'array',
        'effective_from'     => 'date',
        'effective_to'       => 'date',
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

        // Gelöschte Stelle verlässt ihre Einheit — kein Phantom im Stellenplan.
        static::deleted(function (self $model) {
            $model->setUnit(null);
        });
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(\Platform\Core\Models\Team::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(\Platform\Core\Models\User::class);
    }

    /**
     * Einheit (Firma/Abteilung), zu der die Stelle gehört. Die Wahrheit liegt in der Organisation:
     * gespeichert ist das als dimension_link der Stelle auf den Einheits-Knoten, nicht als Spalte.
     */
    /** Knoten-Typen, die eine Stelle tragen können (Organisationseinheiten, keine Personen/Agenten/Umwelt). */
    public const UNIT_TYPES = ['business_unit', 'venture_builder', 'venture', 'service_line', 'strategic_partner',
        'capability_area', 'container', 'board', 'group'];

    /**
     * Einheiten des Teams, an die eine Stelle gehören kann (guarded — organization optional).
     *
     * @return array<int,string> [entity_id => name]
     */
    public static function unitOptions(int $teamId): array
    {
        try {
            return \Platform\Organization\Models\OrganizationEntity::query()
                ->forTeam($teamId)->where('is_active', true)
                ->whereHas('type', fn ($q) => $q->whereIn('code', self::UNIT_TYPES))
                ->orderBy('name')->limit(500)
                ->pluck('name', 'id')->all();
        } catch (\Throwable $e) {
            return [];
        }
    }

    public function unitEntityId(): ?int
    {
        return \Platform\People\Support\OrganizationLink::entityOf('people_job_profile', (int) $this->id);
    }

    /** @throws \InvalidArgumentException wenn der Knoten keine Einheit des Teams ist */
    public function setUnit(?int $entityId): void
    {
        if ($entityId && $entityId > 0 && ! array_key_exists($entityId, self::unitOptions((int) $this->team_id))) {
            throw new \InvalidArgumentException("Knoten {$entityId} ist keine Einheit (Firma/Abteilung/Venture …) dieses Teams.");
        }
        \Platform\People\Support\OrganizationLink::sync('people_job_profile', (int) $this->id,
            $entityId && $entityId > 0 ? $entityId : null, $this->team_id ? (int) $this->team_id : null, auth()->id());
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(EmployeeJobProfile::class, 'job_profile_id');
    }

    /**
     * Benötigte Skills aus dem People-Katalog — mit Level/Pflicht/Reihenfolge.
     */
    public function skillRecords(): BelongsToMany
    {
        return $this->belongsToMany(Skill::class, 'people_job_profile_skills', 'job_profile_id', 'skill_id')
            ->withPivot('level', 'is_required', 'sort_order');
    }

    /**
     * Default-Rollen dieses Profils (aus Organization) — mit Anteil.
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(\Platform\Organization\Models\OrganizationRole::class, 'people_job_profile_roles', 'job_profile_id', 'role_id')
            ->withPivot('percentage_share', 'sort_order')
            ->withTimestamps()
            ->orderBy('people_job_profile_roles.sort_order');
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
