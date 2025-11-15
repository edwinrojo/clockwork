<?php

namespace App\Models;

use App\Enums\TimelogMode;
use App\Enums\TimelogState;
use App\Models\Scopes\ActiveScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Support\Carbon;

class Timelog extends Model
{
    use HasFactory, HasUlids, Prunable;

    public $timestamps = false;

    protected $casts = [
        'time' => 'datetime:Y-m-d H:i:s',
        'shadow' => 'boolean',
        'pseudo' => 'boolean',
        'masked' => 'boolean',
        'recast' => 'boolean',
    ];

    protected $temp = [];

    protected static function booted(): void
    {
        static::addGlobalScope('excludeShadow', fn (Builder $builder) => $builder->where('shadow', false));

        static::addGlobalScope('excludeMasked', fn (Builder $builder) => $builder->where('masked', false));
    
        static::addGlobalScope('excludeRecasted', function (Builder $builder) {
            $builder->whereNot(function (Builder $builder) {
                $builder->orWhereExists(function ($builder) {
                    $builder->from('timelogs as sub')->whereColumn('sub.timelog_id', 'timelogs.id')->where('sub.recast', true);
                });
    
                $builder->orWhere(function ($builder) {
                    $builder->where('recast', true)->whereNull('timelog_id');
                });
            });
        });
    }

    public function state(): Attribute
    {
        return Attribute::make(
            function (TimelogState|int $state) {
                $new = is_int($state) ? (TimelogState::tryFrom($state) ?? TimelogState::UNKNOWN) : $state;

                if ($new === TimelogState::UNKNOWN) {
                    $this->temp['state'] = $state;
                }

                return $new;
            },
            function (TimelogState|int $state) {
                if ($state === TimelogState::UNKNOWN) {
                    return $this->temp['state'];
                }

                return $state instanceof TimelogState ? $state->value : $state;
            },
        )->shouldCache();
    }

    public function mode(): Attribute
    {
        return Attribute::make(
            function (TimelogMode|int $mode) {
                $new = is_int($mode) ? (TimelogMode::tryFrom($mode) ?? TimelogMode::UNKNOWN) : $mode;

                if ($new === TimelogMode::UNKNOWN) {
                    $this->temp['mode'] = $mode;
                }

                return $new;
            },
            function (TimelogMode|int $mode) {
                if ($mode === TimelogMode::UNKNOWN) {
                    return $this->temp['mode'];
                }

                return $mode instanceof TimelogMode ? $mode->value : $mode;
            },
        )->shouldCache();
    }

    public function in(): Attribute
    {
        return Attribute::make(fn (): bool => $this->state->in());
    }

    public function out(): Attribute
    {
        return Attribute::make(fn (): bool => $this->state->out());
    }

    public function uid(): Attribute
    {
        return Attribute::make(fn ($uid): int|string => is_numeric($uid) ? (int) $uid : $uid);
    }

    public function employee(): HasOneThrough
    {
        return $this->hasOneThrough(Employee::class, Enrollment::class, 'timelogs.id', 'id', secondLocalKey: 'employee_id')
            ->join('timelogs', 'employees.id', 'enrollment.employee_id')
            ->whereColumn('timelogs.uid', 'enrollment.uid')
            ->whereColumn('employees.id', 'enrollment.employee_id')
            ->whereColumn('timelogs.device', 'enrollment.device')
            ->withoutGlobalScope(ActiveScope::class);
    }

    public function scanner(): BelongsTo
    {
        return $this->belongsTo(Scanner::class, 'device', 'uid')
            ->withoutGlobalScopes();
    }

    public function original(): BelongsTo
    {
        return $this->belongsTo(Timelog::class, 'timelog_id', 'id')
            ->withoutGlobalScopes();
    }

    public function revision(): HasOne
    {
        return $this->hasOne(Timelog::class, 'timelog_id', 'id')
            ->withoutGlobalScopes();
    }

    public function scopeDay(Builder $query, Carbon|string $date, string $take = 'normal'): void
    {
        $date = $date ? ($date instanceof Carbon ? $date : Carbon::parse($date))->startOfDay() : today();

        $start = $take === 'pre' ? $date->clone()->subDay() : $date->clone();

        $end = $take === 'post' ? $date->clone()->addDay()->endOfDay() : $date->clone()->endOfDay();

        $query->whereBetween('time', [$start, $end]);
    }

    public function scopeMonth(Builder $query, Carbon|string $date): void
    {
        $date = $date ? ($date instanceof Carbon ? $date : Carbon::parse($date))->startOfMonth() : today()->startOfMonth();

        $query->whereBetween('time', [$date, $date->clone()->endOfMonth()]);
    }

    public function scopeFirstHalf(Builder $query): void
    {
        $query->whereDay('time', '<=', 15);
    }

    public function scopeSecondHalf(Builder $query): void
    {
        $query->whereDay('time', '>', 15);
    }

    public function scopeCustomDates(Builder $query, array $dates): void
    {
        $query->where(function ($query) use ($dates) {
            foreach ($dates as $date) {
                $query->orWhereDate('time', $date);
            }
        });
    }

    public function scopeCustomRange(Builder $query, int $day, int $to): void
    {
        $query->whereDay('time', '>=', $day)->whereDay('time', '<=', $to);
    }

    public function prunable(): Builder
    {
        return static::where('time', '<=', now()->subYears(2));
    }
}
