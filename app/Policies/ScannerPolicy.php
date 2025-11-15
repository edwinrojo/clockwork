<?php

namespace App\Policies;

use App\Enums\UserPermission;
use App\Models\User;
use Filament\Facades\Filament;

class ScannerPolicy
{
    public function viewAny(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        return match (Filament::getCurrentOrDefaultPanel()->getId()) {
            'superuser', 'manager' => $user?->hasPermission(UserPermission::SCANNER) ?? false,
            'secretary' => $user?->scanners()->exists(),
            default => false,
        };
    }
}
