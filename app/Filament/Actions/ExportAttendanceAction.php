<?php

namespace App\Filament\Actions;

use App\Actions\ExportAttendance;
use App\Enums\EmploymentStatus;
use App\Enums\EmploymentSubstatus;
use App\Enums\TimelogMode;
use App\Enums\TimelogState;
use App\Models\Scanner;
use App\Models\User;
use Exception;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Process\Exception\ProcessFailedException;

class ExportAttendanceAction extends Action
{
    public bool $transmittal = false;

    public static function make(?string $name = null, bool $transmittal = false): static
    {
        $class = static::class;

        $name = $transmittal ? 'export-attendance-transmittal' : 'export-attendance';

        $static = app($class, ['name' => $name]);

        $static->transmittal = $transmittal;

        $static->configure();

        return $static;
    }

    public function setUp(): void
    {
        parent::setUp();

        $this->requiresConfirmation();

        $this->icon(fn () => $this->transmittal ? 'heroicon-o-clipboard-document-check' : 'heroicon-o-clipboard-document-list');

        $this->modalHeading(fn () => $this->transmittal ? 'Export Transmittal' : 'Export Attendance');

        $this->label(fn () => $this->transmittal ? 'Transmittal' : 'Attendance');

        $this->modalDescription('');

        $this->modalIcon('heroicon-o-document-arrow-down');

        $this->modalWidth('lg');

        $this->closeModalByClickingAway(false);

        $this->schema($this->exportForm());

        $this->action(fn (array $data) => $this->exportAction($data));
    }

    public function exportAction(array $data): StreamedResponse|BinaryFileResponse|Notification
    {
        $actionException = new class extends Exception
        {
            public function __construct(public readonly ?string $title = null, public readonly ?string $body = null)
            {
                parent::__construct();
            }
        };

        try {
            if (count($data['offices']) > 100) {
                throw new $actionException('Too many records', 'To prevent server overload, please select less than 100 records');
            }

            return (new ExportAttendance)
                ->office($data['offices'])
                ->dates($data['dates'])
                ->from($data['from'])
                ->to($data['to'])
                ->scanners($data['scanners'])
                ->states($data['states'])
                ->modes($data['modes'])
                ->status($data['status'])
                ->substatus($data['substatus'])
                ->strict($data['strict'])
                ->current($data['current'])
                ->size($data['size'])
                ->user(@$data['user'] ? User::find($data['user']) : user())
                ->scope(
                    Filament::getCurrentOrDefaultPanel()->getId() !== 'admin'
                        ? function (Builder $query) {
                            $user = user();

                            $query->where(function ($query) use ($user) {
                                $query->orWhereHas('offices', function ($query) use ($user) {
                                    $query->whereIn('offices.id', $user->offices()->select('offices.id'));
                                });

                                $query->orWhereHas('scanners', function ($query) use ($user) {
                                    $query->whereIn('scanners.id', $user->scanners()->select('scanners.id'));
                                });
                            });
                        } : null
                )
                ->transmittal($this->transmittal ? true : ($data['transmittal'] ?? false))
                ->download();
        } catch (ProcessFailedException $exception) {
            $message = 'Failed to export timesheets';

            return Notification::make()
                ->danger()
                ->title($message)
                ->body('Please try again later')
                ->send();
        } catch (Exception $exception) {
            if ($exception instanceof $actionException) {
                return Notification::make()
                    ->danger()
                    ->title($exception->title)
                    ->body($exception->body)
                    ->send();
            }

            throw $exception;
        }
    }

    public function exportForm(): array
    {
        return [
            Tabs::make()
                ->contained(false)
                ->schema([
                    Tab::make('Scanners')
                        ->schema([
                            Select::make('scanners')
                                ->multiple()
                                ->dehydrateStateUsing(fn ($state) => Scanner::whereIn('uid', $state)->orderBy('name')->get())
                                ->options(function () {
                                    $user = user();

                                    return Scanner::query()
                                        ->where(function ($query) use ($user) {
                                            $query->orWhereIn('uid', $user->scanners()->pluck('uid')->toArray());

                                            $query->orWhereHas('employees', function ($query) use ($user) {
                                                $query->whereHas('offices', function ($query) use ($user) {
                                                    $query->whereIn('offices.id', $user->offices()->pluck('offices.id')->toArray());
                                                });
                                            });
                                        })
                                        ->reorder()
                                        ->orderBy('priority', 'desc')
                                        ->orderBy('name')
                                        ->pluck('name', 'uid');
                                })
                                ->getSearchResultsUsing(function ($search) {
                                    $user = user();

                                    return Scanner::query()
                                        ->where('name', 'ilike', "%{$search}%")
                                        ->where(function ($query) use ($user) {
                                            $query->orWhereIn('uid', $user->scanners()->pluck('uid')->toArray());

                                            $query->orWhereHas('employees', function ($query) use ($user) {
                                                $query->whereHas('offices', function ($query) use ($user) {
                                                    $query->whereIn('offices.id', $user->offices()->pluck('offices.id')->toArray());
                                                });
                                            });
                                        })
                                        ->orderBy('name')
                                        ->pluck('name', 'uid');
                                })
                                ->preload(),
                            Group::make()
                                ->columns(2)
                                ->schema([
                                    Select::make('states')
                                        ->multiple()
                                        ->options(collect(TimelogState::cases())->mapWithKeys(fn ($state) => [$state->value => $state->getLabel()])),
                                    Select::make('modes')
                                        ->multiple()
                                        ->options(collect(TimelogMode::cases())->mapWithKeys(fn ($mode) => [$mode->value => $mode->getLabel(1)])),
                                    TextInput::make('from')
                                        ->type('time')
                                        ->rule('date_format:H:i'),
                                    TextInput::make('to')
                                        ->type('time')
                                        ->rule('date_format:H:i'),
                                ]),
                            Repeater::make('dates')
                                ->addActionLabel('Add new')
                                ->reorderable(false)
                                ->grid(2)
                                ->required()
                                ->cloneable()
                                ->simple(
                                    TextInput::make('date')
                                        ->type('date')
                                        ->markAsRequired()
                                        ->rule('required')
                                ),
                        ]),
                    Tab::make('Employees')
                        ->columns(2)
                        ->schema([
                            Radio::make('by')
                                ->hiddenLabel()
                                ->dehydrated(false)
                                ->inline()
                                ->reactive()
                                ->default('office')
                                ->required()
                                ->columnSpanFull()
                                ->options([
                                    'office' => 'Office',
                                    'group' => 'Group',
                                ])
                                ->afterStateUpdated(fn ($set) => $set('offices', null)),
                            Select::make('offices')
                                ->label(fn (Get $get) => $get('by') === 'office' ? 'Offices' : 'Groups')
                                // ->helperText(function (Get $get) {
                                //     if ($get('by') === 'group') {
                                //         $help = <<<'HTML'
                                //             This will include <i><b>all</b></i> employees of the selected group regardless
                                //             of their deployed office or enrolled scanners.
                                //         HTML;

                                //         return str($help)->toHtmlString();
                                //     }
                                // })
                                ->multiple()
                                ->required()
                                ->columnSpanFull()
                                ->dehydrateStateUsing(fn (Get $get, array $state) => ('App\Models\\'.ucfirst($get('by')))::find($state))
                                ->validationAttribute(fn (Get $get) => $get('by') === 'office' ? 'offices' : 'groups')
                                ->options(function (Get $get) {
                                    $admin = Filament::getCurrentOrDefaultPanel()->getId() === 'admin';

                                    return ('App\Models\\'.ucfirst($get('by')))::query()
                                        ->when(! $admin, function (Builder $query) use ($get) {
                                            $user = user();

                                            match ($get('by')) {
                                                'group' => $query->where(function ($query) use ($user) {
                                                    $query->orWhereHas('employees', function ($query) use ($user) {
                                                        $query->whereHas('offices', function ($query) use ($user) {
                                                            $query->whereIn('offices.id', $user->offices()->select('offices.id'));
                                                        });
                                                    });

                                                    $query->orWhereHas('employees', function ($query) use ($user) {
                                                        $query->whereHas('scanners', function ($query) use ($user) {
                                                            $query->whereIn('scanners.id', $user->scanners()->select('scanners.id'));
                                                        });
                                                    });
                                                }),
                                                default => $query->where(function ($query) use ($user) {
                                                    $query->whereIn('id', $user->offices()->select('offices.id'));

                                                    $query->orWhereHas('employees', function ($query) use ($user) {
                                                        $query->whereHas('scanners', function ($query) use ($user) {
                                                            $query->whereIn('scanners.id', $user->scanners()->select('scanners.id'));
                                                        });
                                                    });
                                                })
                                            };
                                        })
                                        ->take(25)
                                        ->orderBy($get('by') === 'office' ? 'code' : 'name')
                                        ->pluck($get('by') === 'office' ? 'code' : 'name', 'id');
                                })
                                ->getSearchResultsUsing(function (Get $get, string $search) {
                                    $admin = Filament::getCurrentOrDefaultPanel()->getId() === 'admin';

                                    return ('App\Models\\'.ucfirst($get('by')))::query()
                                        ->when(! $admin, function ($query) use ($get) {
                                            $user = user();

                                            match ($get('by')) {
                                                'group' => $query->where(function ($query) use ($user) {
                                                    $query->orWhereHas('employees', function ($query) use ($user) {
                                                        $query->whereHas('offices', function ($query) use ($user) {
                                                            $query->whereIn('offices.id', $user->offices()->select('offices.id'));
                                                        });
                                                    });

                                                    $query->orWhereHas('employees', function ($query) use ($user) {
                                                        $query->whereHas('scanners', function ($query) use ($user) {
                                                            $query->whereIn('scanners.id', $user->scanners()->select('scanners.id'));
                                                        });
                                                    });
                                                }),
                                                default => $query->where(function ($query) use ($user) {
                                                    $query->whereIn('id', $user->offices()->select('offices.id'));

                                                    $query->orWhereHas('employees', function ($query) use ($user) {
                                                        $query->whereHas('scanners', function ($query) use ($user) {
                                                            $query->whereIn('scanners.id', $user->scanners()->select('scanners.id'));
                                                        });
                                                    });
                                                })
                                            };
                                        })
                                        ->where(function ($query) use ($get, $search) {
                                            $query->where('name', 'ilike', "%{$search}%");

                                            $query->when($get('by') === 'office', fn ($query) => $query->orWhere('code', 'ilike', "%{$search}%"));
                                        })
                                        ->take(25)
                                        ->orderBy($get('by') === 'office' ? 'code' : 'name')
                                        ->pluck($get('by') === 'office' ? 'code' : 'name', 'id');
                                }),
                            Select::make('status')
                                ->multiple()
                                ->options(collect(EmploymentStatus::cases())->mapWithKeys(fn ($status) => [$status->value => $status->getLabel()])),
                            Select::make('substatus')
                                ->multiple()
                                ->options(collect(EmploymentSubstatus::cases())->mapWithKeys(fn ($substatus) => [$substatus->value => $substatus->getLabel()])),
                            Checkbox::make('strict')
                                ->columnSpanFull()
                                ->label('Strict listing')
                                ->helperText('Filter out all employees who do not have a record on the selected date.'),
                            Checkbox::make('current')
                                ->columnSpanFull()
                                ->label('Currently deployed')
                                ->helperText('Only include employees currently deployed for the selected offices.'),
                        ]),
                    Tab::make('Options')
                        ->schema([
                            Select::make('size')
                                ->live()
                                ->placeholder('Paper Size')
                                ->default(fn ($livewire) => $livewire->filters['folio'] ?? 'folio')
                                ->required()
                                ->options([
                                    'a4' => 'A4 (210mm x 297mm)',
                                    'letter' => 'Letter (216mm x 279mm)',
                                    'folio' => 'Folio (216mm x 330mm)',
                                    'legal' => 'Legal (216mm x 356mm)',
                                ]),
                            Select::make('transmittal')
                                ->hidden($this->transmittal)
                                ->live()
                                ->default(0)
                                ->options([0, 1, 2, 3, 5])
                                ->in([0, 1, 2, 3, 5])
                                ->hintIcon('heroicon-o-question-mark-circle')
                                ->hintIconTooltip('Input the number of copies of transmittal to be generated.'),
                            Select::make('user')
                                ->label('Spoof as')
                                ->visible(fn () => ($user = user())->developer && $user->superuser)
                                ->reactive()
                                ->options(User::take(25)->whereNot('id', Auth::id())->orderBy('name')->pluck('name', 'id'))
                                ->getSearchResultsUsing(fn ($search) => User::take(25)->whereNot('id', Auth::id())->where('name', 'ilike', "%{$search}%")->pluck('name', 'id'))
                                ->searchable(),
                            // Checkbox::make('electronic_signature')
                            //     ->helperText('Electronically sign the document. This does not provide security against tampering.')
                            //     ->default(fn ($livewire) => $livewire->filters['electronic_signature'] ?? false)
                            //     ->live()
                            //     ->afterStateUpdated(fn ($get, $set, $state) => $set('digital_signature', $state ? $get('digital_signature') : false))
                            //     ->rule(fn (Get $get) => function ($attribute, $value, $fail) use ($get) {
                            //         $user = $get('user') ? User::find($get('user')) : user();

                            //         if ($value && ! $user->signature) {
                            //             $fail('Configure your electronic signature first');
                            //         }
                            //     }),
                            // Checkbox::make('digital_signature')
                            //     ->helperText('Digitally sign the document to prevent tampering.')
                            //     ->dehydrated(true)
                            //     ->live()
                            //     ->afterStateUpdated(fn ($get, $set, $state) => $set('electronic_signature', $state ? true : $get('electronic_signature')))
                            //     ->rule(fn (Get $get) => function ($attribute, $value, $fail) use ($get) {
                            //         if (! $value) {
                            //             return;
                            //         }

                            //         if (! $get('electronic_signature')) {
                            //             $fail('Digital signature requires electronic signature');
                            //         }

                            //         $user = $get('user') ? User::find($get('user')) : user();

                            //         if ($user->signature?->certificate === null) {
                            //             $name = $get('user')
                            //                 ? str("$user->name'")->when(! str($user->name)->endsWith('s'), fn ($str) => $str->append('s'))->toString()
                            //                 : 'your';

                            //             return $fail('Please configure '.($get('user') ? $name : 'your').' digital signature certificate first');
                            //         }
                            //     }),
                        ]),
                ]),
        ];
    }
}
