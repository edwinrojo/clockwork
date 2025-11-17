<?php

namespace App\Filament\Actions\TableActions;

use App\Filament\Superuser\Resources\EmployeeResource;
use App\Models\Deployment;
use App\Models\Employee;
use App\Models\Enrollment;
use App\Models\Group;
use App\Models\Member;
use App\Models\Office;
use App\Models\Scanner;
use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class UpdateEmployeeAction extends Action
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->name ??= 'update-employee-enrollment';

        $this->requiresConfirmation();

        $this->modalAlignment('start');

        $this->label('Update');

        $this->icon('heroicon-o-pencil-square');

        $this->modalIcon('heroicon-o-pencil-square');

        $this->modalHeading(fn (Employee $record) => 'Update '.$record->full_name);

        $this->modalDescription(null);

        $this->successNotificationTitle('Employee updated');

        $this->slideOver();

        $this->modalWidth('2xl');

        $this->fillForm(function (Employee $record) {
            return [
                ...$record->withoutRelations()->toArray(),
                'deployments' => $record->deployments()->withoutGlobalScopes()->get()->mapWithKeys(function ($deployment) {
                    return [
                        $deployment->id => [
                            'id' => $deployment->id,
                            'current' => $deployment->current,
                            'active' => $deployment->active,
                            'office_id' => $deployment->office_id,
                            'supervisor_id' => $deployment->supervisor_id,
                        ],
                    ];
                })->values()->all(),
                'enrollments' => $record->enrollments()->withoutGlobalScopes()->get()->mapWithKeys(function ($enrollment) {
                    return [
                        $enrollment->id => [
                            'id' => $enrollment->id,
                            'device' => $enrollment->device,
                            'scanner_id' => $enrollment->scanner_id,
                            'active' => $enrollment->active,
                            'uid' => $enrollment->uid,
                        ],
                    ];
                })->values()->all(),
                'memberships' => $record->members()->withoutGlobalScopes()->get()->mapWithKeys(function ($group) {
                    return [
                        $group->id => [
                            'id' => $group->id,
                            'group_id' => $group->id,
                            'active' => $group->active,
                        ],
                    ];
                })->values()->all(),
            ];
        });

        $this->schema([
            ...EmployeeResource::formSchema(),
            // Tabs::make()
            //     ->contained(true)
            //     ->tabs([
            //         Tab::make('Employee')
            //             ->schema(EmployeeResource::formSchema(compact: true)),
            //         Tab::make('Offices')
            //             ->schema([
            //                 Repeater::make('deployments')
            //                     ->hiddenLabel()
            //                     ->reorderable(false)
            //                     ->addActionLabel('Add office')
            //                     ->schema([
            //                         \Filament\Schemas\Components\Group::make()
            //                             ->columns(2)
            //                             ->schema([
            //                                 Toggle::make('current')
            //                                     ->default(false)
            //                                     ->required()
            //                                     ->inline(false)
            //                                     ->distinct()
            //                                     ->fixIndistinctState()
            //                                     ->afterStateUpdated(function (Set $set, bool $state) {
            //                                         if ($state) {
            //                                             $set('active', true);
            //                                         }
            //                                     }),
            //                                 Toggle::make('active')
            //                                     ->default(true)
            //                                     ->required()
            //                                     ->inline(false)
            //                                     ->rule(fn (Get $get) => function ($attribute, $value, $fail) use ($get) {
            //                                         if (! $value && $get('current')) {
            //                                             $fail('The current deployment must be active.');
            //                                         }
            //                                     }),
            //                             ]),
            //                         Select::make('office_id')
            //                             ->label('Office')
            //                             ->options(function (?string $state) {
            //                                 $offices = Office::take(10)->orderBy('name')->pluck('name', 'id');

            //                                 if ($state) {
            //                                     $office = Office::withoutGlobalScopes()->find($state);
            //                                     if ($office?->name) {
            //                                         $offices->prepend($office->name, $state);
            //                                     }
            //                                 }

            //                                 return $offices;
            //                             })
            //                             ->getSearchResultsUsing(function (string $search) {
            //                                 return Office::orderBy('name')
            //                                     ->where('name', 'ilike', "%$search%")
            //                                     ->orWhere('code', 'ilike', "%$search%")
            //                                     ->pluck('name', 'id');
            //                             })
            //                             ->disabled(fn (Get $get) => $get('id'))
            //                             ->dehydrated()
            //                             ->searchable()
            //                             ->required()
            //                             ->exists('offices', 'id')
            //                             ->distinct()
            //                             ->afterStateUpdated(fn (Set $set) => $set('supervisor_id', null)),
            //                         Select::make('supervisor_id')
            //                             ->label('Supervisor')
            //                             ->options(function (Employee $record, Get $get, ?string $state) {
            //                                 $office = Office::withoutGlobalScopes()->find($get('office_id'));

            //                                 $employees = Employee::query()
            //                                     ->whereNotIn('employees.id', [$record->id, $office?->head?->id])
            //                                     ->whereHas('offices', function ($query) use ($get) {
            //                                         $query->where('offices.id', $get('office_id'))
            //                                             ->where('deployment.active', true)
            //                                             ->where('deployment.current', true);
            //                                     })
            //                                     ->take(25)
            //                                     ->reorder()
            //                                     ->orderBy('name')
            //                                     ->pluck('name', 'id');

            //                                 if ($state) {
            //                                     $employee = Employee::find($state);
            //                                     if ($employee?->name) {
            //                                         $employees->prepend($employee->name, $state);
            //                                     }
            //                                 }

            //                                 return $employees;
            //                             })
            //                             ->getSearchResultsUsing(function (Employee $record, Get $get, ?string $search) {
            //                                 $office = Office::withoutGlobalScopes()->find($get('office_id'));

            //                                 return Employee::query()
            //                                     ->where(function ($query) use ($search) {
            //                                         $query->where('name', 'ilike', "%$search%")
            //                                             ->orWhere('full_name', 'ilike', "%$search%");
            //                                     })
            //                                     ->whereNotIn('employees.id', [$record->id, $office->head?->id])
            //                                     ->whereHas('offices', function ($query) use ($office) {
            //                                         $query->where('offices.id', $office->id)
            //                                             ->where('active', true);
            //                                     })
            //                                     ->take(25)
            //                                     ->reorder()
            //                                     ->orderBy('name')
            //                                     ->pluck('name', 'id');
            //                             })
            //                             ->reactive()
            //                             ->searchable()
            //                             ->exists('employees', 'id')
            //                             ->rule(fn (Get $get) => function ($attribute, $value, $fail) use ($get) {
            //                                 if (
            //                                     Employee::find($value)
            //                                         ->offices()
            //                                         ->where('offices.id', $get('office_id'))
            //                                         ->doesntExist()
            //                                 ) {
            //                                     $fail('Selected employee is invalid.');
            //                                 }
            //                             }),
            //                     ]),
            //             ]),
            //         Tab::make('Scanners')
            //             ->schema([
            //                 Repeater::make('enrollments')
            //                     ->hiddenLabel()
            //                     ->reorderable(false)
            //                     ->columns(5)
            //                     ->addActionLabel('Add scanner')
            //                     ->schema([
            //                         Hidden::make('device'),
            //                         Select::make('scanner_id')
            //                             ->label('Scanner')
            //                             ->options(function (?string $state) {
            //                                 $scanners = Scanner::orderBy('priority', 'desc')->orderBy('name')->pluck('name', 'id');

            //                                 if ($state) {
            //                                     $scanner = Scanner::withoutGlobalScopes()->find($state);
            //                                     if ($scanner && !$scanners->has($state)) {
            //                                         $scanners->prepend($scanner->name, $state);
            //                                     }
            //                                 }

            //                                 return $scanners;
            //                             })
            //                             ->disabled(fn (Get $get) => $get('id'))
            //                             ->dehydrated()
            //                             ->searchable()
            //                             ->required()
            //                             ->rule(function ($attribute, $value, $fail) {
            //                                 if ($value && !Scanner::withoutGlobalScopes()->where('id', $value)->exists()) {
            //                                     $fail('The selected scanner is invalid.');
            //                                 }
            //                             })
            //                             ->distinct()
            //                             ->columnSpan(2)
            //                             ->afterStateUpdated(function (Set $set, string $state) {
            //                                 $scanner = Scanner::withoutGlobalScopes()->find($state);

            //                                 if ($scanner) {
            //                                     $set('device', $scanner->device);
            //                                 }
            //                             }),
            //                         TextInput::make('uid')
            //                             ->label('UID')
            //                             ->markAsRequired()
            //                             ->rule('required')
            //                             ->maxLength(16)
            //                             ->columnSpan(2)
            //                             ->rule(fn (Get $get) => function ($attribute, $value, $fail) use ($get) {
            //                                 if (
            //                                     Enrollment::query()
            //                                         ->where('uid', $value)
            //                                         ->where('scanner_id', $get('scanner_id'))
            //                                         ->whereNot('id', $get('id'))
            //                                         ->exists()
            //                                 ) {
            //                                     $fail('The UID has already been taken.');
            //                                 }
            //                             }),
            //                         Toggle::make('active')
            //                             ->default(true)
            //                             ->required()
            //                             ->inline(false),
            //                     ]),
            //             ]),
            //         Tab::make('Groups')
            //             ->schema([
            //                 Repeater::make('memberships')
            //                     ->hiddenLabel()
            //                     ->reorderable(false)
            //                     ->columns(5)
            //                     ->addActionLabel('Add group')
            //                     ->schema([
            //                         Select::make('group_id')
            //                             ->label('Group')
            //                             ->columnSpan(4)
            //                             ->options(function (?string $state) {
            //                                 $groups = Group::take(10)->orderBy('name')->pluck('name', 'id');

            //                                 if ($state) {
            //                                     $group = Group::withoutGlobalScopes()->find($state);
            //                                     if ($group?->name) {
            //                                         $groups->prepend($group->name, $state);
            //                                     }
            //                                 }

            //                                 return $groups;
            //                             })
            //                             ->getSearchResultsUsing(function (string $search) {
            //                                 return Group::orderBy('name')
            //                                     ->where('name', 'like', '%'.mb_strtolower($search).'%')
            //                                     ->take(25)
            //                                     ->pluck('name', 'id');
            //                             })
            //                             ->disabled(fn (Get $get) => $get('id'))
            //                             ->dehydrated()
            //                             ->searchable()
            //                             ->required()
            //                             // ->rule(fn (Get $get) => function ($attribute, $value, $fail) use ($get) {
            //                             //     // dd($value, $get('id'));

            //                             //     if (
            //                             //         $value &&
            //                             //         Group::withoutGlobalScopes()
            //                             //             ->where('id', $value)
            //                             //             ->whereNot('id', $get('id'))
            //                             //             ->exists()
            //                             //     ) {
            //                             //         $fail('The selected scanner is invalid.');
            //                             //     }
            //                             // })
            //                             ->distinct(),
            //                         Toggle::make('active')
            //                             ->required()
            //                             ->inline(false)
            //                             ->default(true),
            //                     ]),
            //             ]),
            //     ]),
        ]);

        $this->action(function (Employee $record, array $data) {
            DB::transaction(function () use ($record, $data) {
                $record->fill($data)->save();

                $enrollments = collect($data['enrollments'] ?? [])->map(function ($data) use ($record) {
                    return [
                        'id' => $data['id'] ?? strtolower(str()->ulid()),
                        'employee_id' => $record->id,
                        ...$data,
                    ];
                });

                Enrollment::upsert($enrollments->toArray(), ['id'], ['scanner_id', 'device', 'uid', 'active']);

                Enrollment::query()
                    ->where('employee_id', $record->id)
                    ->whereNotIn('id', $enrollments->pluck('id'))
                    ->delete();

                $deployments = collect($data['deployments'] ?? [])->map(function ($data) use ($record) {
                    return [
                        'id' => $data['id'] ?? strtolower(str()->ulid()),
                        'employee_id' => $record->id,
                        ...$data,
                    ];
                });

                Deployment::upsert($deployments->toArray(), ['id'], ['supervisor_id', 'office_id', 'current', 'active']);

                Deployment::query()
                    ->where('employee_id', $record->id)
                    ->whereNotIn('id', $deployments->pluck('id'))
                    ->delete();

                $memberships = collect($data['memberships'] ?? [])->map(function ($data) use ($record) {
                    return [
                        'id' => $data['id'] ?? strtolower(str()->ulid()),
                        'employee_id' => $record->id,
                        ...$data,
                    ];
                });

                Member::upsert($memberships->toArray(), ['id'], ['group_id', 'active']);

                Member::query()
                    ->where('employee_id', $record->id)
                    ->whereNotIn('id', $memberships->pluck('id'))
                    ->delete();
            });

            // $this->sendSuccessNotification();
        });
    }
}
