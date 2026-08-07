<?php

namespace Platform\People\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Symfony\Component\Uid\UuidV7;

/**
 * EmployeeSkill — der Faehigkeits-Bestand: Person -> Skill mit Level und
 * Zertifizierungsdatum. Das ist der Kern dessen, was aus Organization
 * hierher wandert.
 */
class EmployeeSkill extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'people_employee_skills';

    protected $fillable = [
        'uuid',
        'team_id',
        'employee_id',
        'skill_id',
        'level',
        'certified_at',
        'notes',
    ];

    protected $casts = [
        'certified_at' => 'date',
    ];

    public const LEVELS = ['basic', 'advanced', 'expert'];

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

    public function skill(): BelongsTo
    {
        return $this->belongsTo(Skill::class, 'skill_id');
    }
}
