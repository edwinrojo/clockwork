<?php

namespace App\Policies;

use App\Enums\UserPermission;
use App\Models\User;
use Filament\Facades\Filament;

class OfficePolicy
{
    public function viewAny(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        return match (Filament::getCurrentOrDefaultPanel()->getId()) {
            'superuser', 'manager' => $user?->hasPermission(UserPermission::OFFICE) ?? false,
            'secretary' => $user?->offices()->exists(),
            default => false,
        };
    }
}
