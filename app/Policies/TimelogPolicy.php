<?php

namespace App\Policies;

use App\Enums\UserPermission;
use App\Models\User;
use Filament\Facades\Filament;

class TimelogPolicy
{
    public function viewAny(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        return match (Filament::getCurrentOrDefaultPanel()->getId()) {
            'superuser' => $user?->hasPermission(UserPermission::TIMELOG) ?? false,
            'secretary' => true,
            default => false,
        };
    }
}
