<?php

namespace App\Filament\Superuser\Resources\ScannerResource\Pages;

use App\Actions\FlushScannerTimelogs;
use App\Filament\Superuser\Resources\ScannerResource;
use App\Models\Scanner;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditScanner extends EditRecord
{
    protected static string $resource = ScannerResource::class;

    public function getContentTabLabel(): ?string
    {
        return 'Scanner Settings';
    }

    public function hasCombinedRelationManagerTabsWithContent(): bool
    {
        return true;
    }

    protected function getHeaderActions(): array
    {
        return [
            ActionGroup::make([
                DeleteAction::make(),
                ForceDeleteAction::make()
                    ->modalDescription(function (Scanner $record) {
                        if ($record->timelogs()->withoutGlobalScopes()->doesntExist()) {
                            return 'Are you sure you would like to do this?';
                        }

                        return "Are you sure you want to delete this scanner {$record->name}? This will also delete all related resources and cannot be undone.";
                    })
                    ->schema([
                        TextInput::make('password')
                            ->hidden(fn (Scanner $record) => $record->timelogs()->withoutGlobalScopes()->doesntExist())
                            ->label('Password')
                            ->password()
                            ->currentPassword()
                            ->markAsRequired()
                            ->rules(['required']),
                    ]),
                Action::make('Flush')
                    ->color('danger')
                    ->icon('heroicon-o-trash')
                    ->requiresConfirmation()
                    ->modalDescription('Are you sure you want to flush all the scanner\'s timelogs? This cannot be undone.')
                    ->schema([
                        TextInput::make('month')
                            ->type('month'),
                        TextInput::make('password')
                            ->label('Password')
                            ->password()
                            // ->currentPassword()
                            ->markAsRequired()
                            ->rules(['required']),
                    ])
                    ->action(function (array $data, Scanner $record, FlushScannerTimelogs $flusher) {
                        @[$year, $month] = @explode('-', $data['month']);

                        $flusher($record, year: $year ?: null, month: $month ?: null);

                        Notification::make()
                            ->warning()
                            ->title('Scanner timelogs are flushed')
                            ->send();
                    }),
                RestoreAction::make(),
            ]),
        ];
    }
}
