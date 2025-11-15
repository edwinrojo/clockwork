<?php

namespace App\Filament\Manager\Resources\TimesheetResource\Pages;

use App\Filament\Manager\Resources\TimesheetResource;
use App\Jobs\CertifyTimesheets;
use App\Models\Employee;
use App\Models\Timesheet;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\ViewField;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Js;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;

class VerifyTimesheets extends Page
{
    use InteractsWithForms;

    #[Locked,Url]
    public ?string $timesheets = null;

    public ?array $data = [];

    protected static string $resource = TimesheetResource::class;

    protected string $view = 'filament.manager.resources.timesheet-resource.pages.verify-timesheets';

    public static function canAccess(array $parameters = []): bool
    {
        return in_array(Filament::getCurrentOrDefaultPanel()->getId(), ['director', 'leader']) &&
            parent::canAccess($parameters) &&
            request()->hasValidSignature();
    }

    public function mount(): void
    {
        try {
            $decrypted = decrypt($this->timesheets);

            $timesheets = Timesheet::with(['employee', 'attachments'])->find($decrypted);

            abort_unless($timesheets->count() === count($decrypted), 404);
        } catch (DecryptException) {
            abort(403);
        }

        $this->form->fill(collect($decrypted)->mapWithKeys(fn ($id) => [$id => true])->toArray());
    }

    public function save(): mixed
    {
        $data = $this->form->getState();

        if (empty($data)) {
            return Notification::make()
                ->title('Nothing to verify')
                ->body('Selected timesheets are either already verified or not yet ready for verification. Skipping.')
                ->warning()
                ->send();
        }

        $selected = array_keys(array_filter($data));

        if (empty($selected)) {
            return Notification::make()
                ->title('Nothing to verify')
                ->body('Please select at least one timesheet to verify.')
                ->warning()
                ->send();
        }

        CertifyTimesheets::dispatch(array_keys(array_filter($data)), Filament::getCurrentOrDefaultPanel()->getId(), Auth::id());

        Notification::make()
            ->title('Timesheet verification in progress')
            ->body('We will notify you once the verification is completed.')
            ->success()
            ->send();

        return $this->redirect(static::getResource()::getUrl());
    }

    public function form(Schema $schema): Schema
    {
        try {
            $decrypted = decrypt($this->timesheets);

            $timesheets = Timesheet::with(['employee', 'attachments', 'export.signers'])->find($decrypted);

            abort_unless($timesheets->count() === count($decrypted), 404);
        } catch (DecryptException) {
            abort(403);
        }

        $timesheets = Timesheet::find($decrypted)->map(function (Timesheet $timesheet) {
            $panel = Filament::getCurrentOrDefaultPanel()->getId();

            $help = match (true) {
                $timesheet->export->signers->contains(fn ($sign) => $sign->meta === $panel) => 'Already verified.',
                $panel === 'director' && @$timesheet->details['leader'] && $timesheet->export->signers->doesntContain(fn ($sign) => $sign->meta === 'leader') => ucfirst(settings('leader')).' verification required.',
                $panel === 'leader' && @$timesheet->details['director'] && $timesheet->export->signers->doesntContain(fn ($sign) => $sign->meta === 'director') => ucfirst(settings('director')).' verification required.',
                default => null,
            };

            return Group::make([
                Checkbox::make($timesheet->id)
                    ->disabled($help !== null)
                    ->helperText($help !== null ? "$help (skipping)" : null)
                    ->label("{$timesheet->employee->name} ({$timesheet->period})"),
                Group::make([
                    Group::make([
                        ViewField::make('preview')
                            ->dehydrated(false)
                            ->view('filament.validation.pages.csc', [
                                'timesheets' => [$timesheet->setSpan($timesheet->span)],
                                'left' => true,
                                'styles' => false,
                                'month' => false,
                                'full' => true,
                                'title' => 'Timesheet',
                            ]),
                    ])->columnSpan(2),
                    ViewField::make('attachments')
                        ->dehydrated(false)
                        ->columnSpan(3)
                        ->view('filament.validation.pages.attachments', [
                            'attachments' => $timesheet->attachments,
                        ]),
                ])->columns(5),
            ]);
        });

        return $schema
            ->statePath('data')
            ->schema(array_merge($timesheets->toArray(), [
                Checkbox::make('confirmation')
                    ->markAsRequired()
                    ->accepted()
                    ->extraAttributes(['class' => 'self-start mt-1'])
                    ->validationMessages(['accepted' => 'You must certify first.'])
                    ->dehydrated(false)
                    ->rule(fn () => function ($attribute, $value, $fail) {
                        /** @var Employee */
                        $user = Auth::user();

                        if ($user->signature === null || $user->signature->certificate === null || $user->signature->password === null) {
                            return $fail('You must have to configure your digital signature first.');
                        }
                    })
                    ->label(function () {
                        return <<<'LABEL'
                            I verify that the information provided is accurate and correctly reports
                            the hours of work performed in accordance with the prescribed office hours
                        LABEL;
                    }),
            ]));
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label('Verify')
                ->submit('save')
                ->keyBindings(['mod+s']),
            Action::make('cancel')
                ->color('gray')
                ->label(__('filament-panels::resources/pages/edit-record.form.actions.cancel.label'))
                ->alpineClickHandler(
                    'document.referrer ? window.history.back() : (window.location.href = '.
                    Js::from($this->previousUrl ?? static::getResource()::getUrl()).')'
                ),
        ];
    }
}
