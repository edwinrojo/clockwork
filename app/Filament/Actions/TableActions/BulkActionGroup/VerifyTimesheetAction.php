<?php

namespace App\Filament\Actions\TableActions\BulkActionGroup;

use App\Filament\Actions\TableActions\CertifyTimesheetAction;
use App\Forms\Components\TimesheetOption;
use App\Models\Employee;
use App\Models\Timesheet;
use App\Models\User;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Facades\Filament;
use Filament\Forms\Components\Checkbox;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;

class VerifyTimesheetAction extends BulkActionGroup
{
    protected string|false|null $level = null;

    protected array $periods = [
        '1st',
        '2nd',
        'full',
    ];

    public static function make(array $actions = []): static
    {
        $static = app(static::class, ['actions' => $actions]);
        $static->configure();

        return $static;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->level = match (Filament::getCurrentOrDefaultPanel()->getId()) {
            'director' => 'head',
            'supervisor' => 'supervisor',
            'employee' => null,
            default => false,
        };

        $this->label('Verify');

        $this->icon('gmdi-fact-check-o');

        $this->actions(array_map(fn ($period) => $this->verifyAction($period), $this->periods));
    }

    protected function verifyAction(string $period): BulkAction
    {
        $label = match ($period) {
            'full' => 'Full Month',
            default => "{$period} Half",
        };

        return BulkAction::make("verify-{$period}")
            ->label($label)
            ->requiresConfirmation()
            ->modalIcon('gmdi-fact-check-o')
            ->modalHeading('Verification')
            ->modalDescription('Verify selected timesheet\'s '.strtolower($label).(in_array($period, ['1st', '2nd']) ? ' of the month' : ''))
            ->slideOver()
            ->successNotificationTitle('Timesheets verified successfully.')
            ->failureNotificationTitle('Verification failed.')
            ->modalWidth('max-w-lg')
            ->closeModalByClickingAway()
            ->schema(function (Collection $records) use ($period) {
                $records = $records->toQuery()
                    ->whereHas('exports', fn ($q) => $q->where('details->period', $period)->whereNull("details->verification->{$this->level}->at"))
                    ->get()
                    ->each
                    ->setSpan($period);

                return [
                    TimesheetOption::make('timesheets')
                        ->bulkToggleable()
                        ->records($records)
                        ->options($records->mapWithKeys(fn ($record) => [$record->id => $record->employee->name])->toArray())
                        ->label('Timesheets')
                        ->required(),
                    Checkbox::make('confirmation')
                        ->label(fn () => 'I verify that the information is accurate and correct report of the hours of work performed.')
                        ->hidden($records->isEmpty())
                        ->markAsRequired()
                        ->accepted()
                        ->rule(fn () => function ($attribute, $value, $fail) {
                            /** @var User|Employee */
                            $user = Auth::user();

                            if (empty($user->signature->certificate)) {
                                return $fail('You must have a valid digital signature to verify.');
                            }
                        })
                        ->validationMessages(['accepted' => 'You must '.(in_array($this->level, ['head', 'supervisor']) ? 'verify' : 'certify').' first.']),
                ];
            })
            ->action(function (BulkAction $action, Collection $records, array $data) use ($period) {
                $records = $records->toQuery()
                    ->whereIn('id', $data['timesheets'])
                    ->whereHas('exports', fn ($q) => $q->where('details->period', $period))
                    ->with(['employee', 'exports' => fn ($q) => $q->where('details->period', $period)])
                    ->get();

                if ($records->isEmpty()) {
                    $action->sendFailureNotification();

                    return;
                }
                $timestamp = now()->format('Y-m-d H:i:s');

                $signer = fn ($record) => app(CertifyTimesheetAction::class, ['name' => 'signer'])->sign($record, $this->level, $timestamp);

                $records->each(function (Timesheet $record) use ($signer, $timestamp) {

                    $export = $record->exports->first();

                    $signed = $signer(base64_encode($export->content));

                    $export->forceFill([
                        'content' => $signed,
                        "details->verification->{$this->level}->at" => $timestamp,
                    ])->save();
                });

                $action->sendSuccessNotification();
            });
    }
}
