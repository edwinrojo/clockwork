<?php

namespace App\Filament\Actions;

use App\Actions\FetchHolidays;
use App\Models\Holiday;
use Exception;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;

class FetchHolidaysAction extends Action
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->name ??= 'fetch-holidays';

        $this->requiresConfirmation();

        $this->modalIcon('heroicon-m-arrow-down-tray');

        $this->modalDescription('Fetch holidays from an external API. This will only fetch national holidays in the Philippines for the specified year.');

        $this->schema([
            TextInput::make('year')
                ->label('Year')
                ->default(now()->year)
                ->type('number')
                ->markAsRequired()
                ->rules(['numeric', 'required']),
        ]);

        $this->action(function (FetchHolidays $fetcher, array $data) {
            try {
                $response = $fetcher($data['year']);

                if (is_null($response)) {
                    throw new Exception('External api response returned null.');
                }

                $holidays = collect($response)->map(function ($holiday) {
                    return [
                        'name' => $holiday['name'],
                        'date' => $holiday['date']['iso'],
                        'type' => mb_strtolower($holiday['primary_type']) === 'regular holiday' ? 'regular-holiday' : 'special-holiday',
                        'remarks' => $holiday['description'],
                        'created_by' => Auth::id(),
                    ];
                })->toArray();
            } catch (Exception $e) {
                Notification::make()
                    ->danger()
                    ->title('Failed to fetch holidays')
                    ->body($e->getMessage())
                    ->send();

                return;
            }

            Holiday::upsert($holidays, ['name', 'date', 'type'], ['remarks']);

            Notification::make()
                ->success()
                ->title('Holiday fetch successful')
                ->body('Successfully fetched holidays from an external API with '.count($holidays).' holidays.')
                ->send();
        });
    }
}
