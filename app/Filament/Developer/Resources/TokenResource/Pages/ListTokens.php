<?php

namespace App\Filament\Developer\Resources\TokenResource\Pages;

use App\Filament\Developer\Resources\TokenResource;
use App\Models\Token;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\DB;

class ListTokens extends ListRecords
{
    protected static string $resource = TokenResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('New Token')
                ->requiresConfirmation()
                ->modalDescription('Thise token will only be valid for the current year.')
                ->schema([
                    TextInput::make('name')
                        ->default(today()->year)
                        ->label('Year')
                        ->markAsRequired()
                        ->dehydrated(false)
                        ->readOnly()
                        ->rule(fn () => function ($a, $v, $f) {
                            if (
                                Token::query()
                                    ->where('expires_at', now()->endOfYear())
                                    ->where('tokenable_type', User::class)
                                    ->where('tokenable_id', auth()->id())
                                    ->exists()
                            ) {
                                return $f('An active token for this year already exists.');
                            }
                        }),
                ])
                ->action(function () {
                    $token = DB::transaction(fn () => User::find(auth()->id())->createToken(now()->year, ['*'], now()->endOfYear()));

                    $this->replaceMountedAction('preview', ['token' => $token->plainTextToken]);
                }),
            Action::make('preview')
                ->requiresConfirmation()
                ->extraAttributes(['class' => 'hidden'])
                ->modalDescription('This plain text token will only be shown once. Please copy it to a safe place.')
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Close')
                ->closeModalByClickingAway(false)
                ->schema(fn ($arguments) => [
                    TextInput::make('token')
                        ->default($arguments['token'])
                        ->readOnly()
                        ->dehydrated(false)
                        ->suffixAction(
                            Action::make('copy')
                                ->icon('heroicon-s-clipboard-document-check')
                                ->action(fn ($livewire, $state) => $livewire->js('window.navigator.clipboard.writeText("'.$state.'");')),
                        ),
                ]),
        ];
    }
}
