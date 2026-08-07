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
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(\Platform\Core\Models\Team::class);
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
}
