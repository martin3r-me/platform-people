<?php

namespace Platform\People\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Symfony\Component\Uid\UuidV7;

/**
 * Employment — das Beschaeftigungsverhaeltnis (schlank, ohne Payroll/Tarif).
 */
class Employment extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'people_employments';

    protected $fillable = [
        'uuid',
        'team_id',
        'employee_id',
        'employment_type',
        'fte',
        'weekly_hours',
        'started_on',
        'ended_on',
        'status',
        'note',
    ];

    protected $casts = [
        'fte' => 'decimal:2',
        'weekly_hours' => 'decimal:2',
        'started_on' => 'date',
        'ended_on' => 'date',
    ];

    public const TYPES = ['regular', 'part_time', 'temporary', 'marginal', 'freelance'];

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

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}
