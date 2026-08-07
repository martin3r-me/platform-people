<?php

namespace Platform\People\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Symfony\Component\Uid\UuidV7;

/**
 * Skill — Kompetenz-Katalog. Vereint fachliche und soziale Faehigkeiten ueber
 * `category`. Der Bestand pro Person haengt via EmployeeSkill daran.
 */
class Skill extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'people_skills';

    protected $fillable = [
        'uuid',
        'team_id',
        'name',
        'category',
        'description',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public const CATEGORIES = ['technical', 'methodical', 'domain', 'social'];

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

    public function employeeSkills(): HasMany
    {
        return $this->hasMany(EmployeeSkill::class, 'skill_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
