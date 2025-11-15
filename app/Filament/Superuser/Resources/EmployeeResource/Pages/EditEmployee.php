<?php

namespace App\Filament\Superuser\Resources\EmployeeResource\Pages;

use App\Filament\Superuser\Resources\EmployeeResource;
use App\Models\Employee;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Validation\Rules\Password;

class EditEmployee extends EditRecord
{
    protected static string $resource = EmployeeResource::class;

    public function getContentTabLabel(): ?string
    {
        return 'Employee Account';
    }

    public function hasCombinedRelationManagerTabsWithContent(): bool
    {
        return true;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('reset_password')
                ->visible(fn (Employee $record) => ! empty($record->password))
                ->requiresConfirmation()
                ->modalIcon('heroicon-o-shield-check')
                ->modalHeading('Password Reset')
                ->modalDescription('')
                ->successNotificationTitle('Password reset successful')
                ->schema([
                    TextInput::make('password')
                        ->columnSpan(2)
                        ->password()
                        ->rule(Password::default())
                        ->rule('required')
                        ->markAsRequired()
                        ->requiredWith('passwordConfirmation')
                        ->same('passwordConfirmation'),
                    TextInput::make('passwordConfirmation')
                        ->columnSpan(2)
                        ->password()
                        ->rule('required')
                        ->markAsRequired()
                        ->requiredWith('password')
                        ->dehydrated(false),
                ])
                ->action(function (Action $action, Employee $record, array $data) {
                    $record->update(['password' => $data['password']]);

                    $action->sendSuccessNotification();
                }),
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
