<?php

namespace App\Traits;

use App\Enums\RequestStatus;
use App\Models\Request;
use App\Models\Route;
use App\Models\Schedule;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

trait Requestable
{
    public function application(): MorphOne
    {
        return $this->morphOne(Request::class, 'requestable')
            ->ofMany(['id' => 'max'], fn ($query) => $query->where('status', RequestStatus::REQUEST)->where('step', 1));
    }

    public function request(): MorphOne
    {
        return $this->morphOne(Request::class, 'requestable')
            ->latestOfMany();
    }

    public function requests(): MorphMany
    {
        return $this->morphMany(Request::class, 'requestable')
            ->orderBy('id', 'desc');
    }

    public function requestor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requestor_id');
    }

    public function route(): HasOne
    {
        return $this->hasOne(Route::class, 'id')
            ->orWhereRaw('TRUE')
            ->where(fn ($query) => $query->where('model', $this::class));
    }

    public function nextRoute(): Attribute
    {
        return Attribute::make(
            function () {
                $request = $this->request;

                if (isset($request) && $request->status === RequestStatus::RETURN) {
                    return $this->route->next(null);
                }

                return $this->route->next($request?->step);
            },
        )->shouldCache();
    }

    public function requested(): Attribute
    {
        return Attribute::make(
            fn () => $this->requestable ? $this->request->requested : null,
        )->shouldCache();
    }

    public function cancelled(): Attribute
    {
        return Attribute::make(
            fn () => $this->requestable ? $this->request->cancelled : null,
        )->shouldCache();
    }

    public function rejected(): Attribute
    {
        return Attribute::make(
            fn () => $this->requestable ? $this->request->rejected : null,
        )->shouldCache();
    }

    public function respondible(): Attribute
    {
        return Attribute::make(
            function () {
                $panel = Filament::getCurrentOrDefaultPanel()->getId();

                if ($this::class === Schedule::class) {
                    if ($this->global) {
                        return false;
                    }

                    if (
                        $this->relationLoaded('request') &&
                        is_null($this->request) ||
                        $this->request()->doesntExist()
                    ) {
                        return true;
                    }

                    if (! in_array($this->request->status, [RequestStatus::REQUEST, RequestStatus::ESCALATE, RequestStatus::DEFLECT])) {
                        return false;
                    }

                    if ($this->request->deflected) {
                        return $this->route->final() === $panel;
                    }

                    if ($this->request->toward === $panel) {
                        return true;
                    }
                }

                return false;
            }
        );
    }

    public function deflectable(): Attribute
    {
        return Attribute::make(
            function () {
                if (! $this->respondible) {
                    return null;
                }

                return $this->request->escalated;
            }
        );
    }

    public function requestable(): Attribute
    {
        return Attribute::make(
            fn () => match ($this::class) {
                Schedule::class => ! $this->global && in_array($this->request?->status, [null, RequestStatus::REJECT]),
                default => true,
            },
        )->shouldCache();
    }

    public function drafted(): Attribute
    {
        return Attribute::make(
            fn () => $this->requests()->doesntExist(),
        )->shouldCache();
    }

    public function cancellable(): Attribute
    {
        return Attribute::make(
            fn () => ! $this->global ? in_array($this->request?->status, [RequestStatus::REQUEST]) : null
        )->shouldCache();
    }

    public function scopeCompletelyApproved(Builder $query): void
    {
        $query->whereHas('request', function (Builder $query) {
            $query->where('status', RequestStatus::APPROVE);

            $query->where('completed', true);
        });
    }
}
