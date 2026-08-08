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
        'employer_id',
        'department_entity_id',
        'employment_type',
        'fte',
        'weekly_hours',
        'weekly_days',
        'started_on',
        'ended_on',
        'status',
        'is_fixed_term',
        'fixed_term_end_date',
        'probation_end_date',
        'annual_vacation_days',
        'wage_type',
        'gross_amount',
        'work_location',
        'note',
    ];

    protected $casts = [
        'fte' => 'decimal:2',
        'weekly_hours' => 'decimal:2',
        'weekly_days' => 'decimal:1',
        'started_on' => 'date',
        'ended_on' => 'date',
        'is_fixed_term' => 'boolean',
        'fixed_term_end_date' => 'date',
        'probation_end_date' => 'date',
        'annual_vacation_days' => 'integer',
        'gross_amount' => 'decimal:2',
    ];

    public const TYPES = ['regular', 'part_time', 'temporary', 'marginal', 'freelance'];

    public const WAGE_TYPES = ['salary', 'hourly'];

    /** Lesbare Labels für die Anstellungsart. */
    public const TYPE_LABELS = [
        'regular'   => 'Vollzeit',
        'part_time' => 'Teilzeit',
        'temporary' => 'Befristet',
        'marginal'  => 'Minijob',
        'freelance' => 'Freelance',
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

    public function employer(): BelongsTo
    {
        return $this->belongsTo(Employer::class, 'employer_id');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}
