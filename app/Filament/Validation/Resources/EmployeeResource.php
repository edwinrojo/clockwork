<?php

namespace App\Filament\Validation\Resources;

use App\Filament\Validation\Resources\EmployeeResource\Pages\PreviewTimesheet;
use App\Models\Employee;
use Filament\Resources\Resource;

class EmployeeResource extends Resource
{
    protected static ?string $model = Employee::class;

    protected static ?string $slug = 'timesheet';

    protected static bool $shouldSkipAuthorization = true;

    protected static bool $shouldRegisterNavigation = false;

    public static function getPages(): array
    {
        return [
            'index' => PreviewTimesheet::route('/'),
            'preview' => PreviewTimesheet::route('/preview/{record}'),
        ];
    }
}
