<?php

namespace App\Filament\Superuser\Resources\UserResource\Pages;

use App\Filament\Superuser\Resources\UserResource;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Validation\Rules\Password;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('reset_password')
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
                ->action(function (Action $component, User $record, array $data) {
                    $record->update(['password' => $data['password']]);

                    $component->sendSuccessNotification();
                }),
            DeleteAction::make(),
            RestoreAction::make(),
            ForceDeleteAction::make(),
        ];
    }
}
