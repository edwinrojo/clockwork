<?php

namespace App\Filament\Superuser\Resources;

use App\Filament\Superuser\Resources\TimesheetResource\Pages\ListTimesheets;
use App\Models\Employee;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

class TimesheetResource extends Resource
{
    use InteractsWithPageFilters;

    protected static ?string $model = Employee::class;

    protected static string|\BackedEnum|null $navigationIcon = 'gmdi-document-scanner-o';

    protected static ?string $modelLabel = 'Timesheet';

    public static function getModel(): string
    {
        return static::$model ?? (string) str(class_basename(static::class))
            ->beforeLast('Resource')
            ->prepend('App\\Models\\');
    }

    public static function form(Schema $schema): Schema
    {
        return $form
            ->schema([
                //
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTimesheets::route('/'),
        ];
    }
}
