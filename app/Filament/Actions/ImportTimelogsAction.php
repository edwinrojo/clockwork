<?php

namespace App\Filament\Actions;

use App\Jobs\ImportTimelogs;
use App\Models\Scanner;
use Closure;
use DateTime;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Support\LazyCollection;
use League\Csv\Reader;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class ImportTimelogsAction extends Action
{
    protected bool $onlyAssigned = false;

    protected function setUp(): void
    {
        parent::setUp();

        $this->name ??= 'import-timelogs';

        $this->requiresConfirmation();

        $this->icon('heroicon-m-arrow-up-tray');

        $this->groupedIcon('heroicon-m-arrow-up-tray');

        $this->modalIcon('heroicon-o-arrow-up-tray');

        $this->modalDescription('Import downloaded timelogs to the system.');

        $this->closeModalByClickingAway(false);

        $this->action(function (array $data) {
            if (is_null($data['files'])) {
                return;
            }

            $devices = [];

            foreach ($data['files'] as $file) {
                $first = Reader::createFromStream(fopen($file->getRealPath(), mode: 'r'))
                    ->addFormatter(fn ($row) => array_map('trim', $row))
                    ->setDelimiter("\t")
                    ->first();

                $devices[] = $first[2];

                ImportTimelogs::dispatch($first[2], $file->getRealPath(), $file->getClientOriginalName(), $data['month']);
            }

            $devices = implode(', ', $devices);

            Notification::make()
                ->success()
                ->title('Upload successful')
                ->body(str("We'll notify you once the timelogs for devices <b>{$devices}</b> have been imported.")->toHtmlString())
                ->send();
        });

        $this->schema([
            Radio::make('process')
                ->hidden()
                ->label('Process')
                ->live()
                ->dehydrated(false)
                ->default(true)
                ->options([
                    false => 'Ignore',
                    true => 'Enable',
                ])
                ->descriptions([
                    false => 'Disable processing.',
                    true => 'Only process timelogs of the specified month.',
                ])
                ->afterStateUpdated(function (Get $get, Set $set, bool $state, $livewire) {
                    if ($state) {
                        $set('month', $get('month') ?? today()->format('Y-m'));

                        $livewire->validateOnly('month');
                    }
                }),
            TextInput::make('month')
                ->visible(fn (Get $get) => $get('process'))
                ->default(today()->day > 15 ? today()->startOfMonth()->format('Y-m') : today()->subMonth()->format('Y-m'))
                ->rules(['required'])
                ->live()
                ->markAsRequired(true)
                ->type('month')
                ->helperText(function () {
                    return 'Timelogs outside the specified month will be ignored.';
                }),
            FileUpload::make('files')
                ->helperText('Please ensure that the device UID in the file matches the device UID in the system to avoid errors.')
                // ->afterStateUpdated(fn ($livewire) => $livewire->resetValidation())
                ->multiple()
                ->storeFiles(false)
                ->visibility('private')
                ->maxSize(8096)
                ->required()
                ->rules([
                    fn () => function (string $attribute, TemporaryUploadedFile $value, Closure $fail) {
                        $file = $value->getCLientOriginalName();

                        if (mime_content_type($value->getRealPath()) !== 'text/plain') {
                            $fail("File uploaded {$file} is invalid");
                        }

                        $rows = Reader::createFromStream(fopen($value->getRealPath(), mode: 'r'))
                            ->addFormatter(fn ($row) => array_map('trim', $row))
                            ->setDelimiter("\t");

                        if (LazyCollection::make(fn () => yield from $rows->select(2))->unique()->count() > 1) {
                            $fail('Conflicting device UID in file uploaded');
                        }

                        if (
                            LazyCollection::make(fn () => yield from $rows)
                                ->some(fn ($row) => count($row) !== 6 ||
                                    DateTime::createFromFormat('Y-m-d H:i:s', $row[1]) === false ||
                                    DateTime::createFromFormat('Y-m-d H:i:s', $row[1])->format('Y-m-d H:i:s') !== $row[1] ||
                                    ! is_numeric($row[2]) ||
                                    ! is_numeric($row[3]) ||
                                    ! is_numeric($row[4]) ||
                                    ! is_numeric($row[5])
                                )
                        ) {
                            $fail("File uploaded {$file} is invalid");
                        }

                        $device = $rows->first()[2];

                        $number = substr_replace($file, '', strrpos($file, '_attlog.dat'), strlen('_attlog.dat'));

                        if (is_numeric($number) && $number <= 255 && $number !== $device) {
                            $fail("File uploaded {$file} is invalid");
                        }

                        if ($device) {
                            if (
                                Scanner::where('uid', $device)
                                    ->when($this->onlyAssigned, fn ($query) => $query->whereIn('scanners.uid', auth()->user()->scanners->pluck('uid')))
                                    ->doesntExist()
                            ) {
                                $fail("No device found for these records from device {$device} in file $file");
                            }
                        } else {
                            $fail("File uploaded {$file} is invalid");
                        }
                    },
                ]),
        ]);

        $this->modalSubmitActionLabel('Import');

        $this->modalCancelActionLabel('Close');
    }

    public function onlyAssigned(bool $true = true)
    {
        $this->onlyAssigned = $true;

        return $this;
    }
}
