<?php

namespace App\Filament\Actions\Request\TableActions;

use App\Enums\RequestStatus;
use App\Enums\WorkArrangement;
use App\Models\Request;
use App\Models\Schedule;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ViewField;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class RespondAction extends Action
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->name ??= 'respond-request';

        $this->label('Respond');

        $this->modalIcon('gmdi-rule-folder-o');

        $this->modalDescription('What action would you like to take on this request?');

        $this->modalWidth('xl');

        $this->disabled(fn (Request $record) => ! $record->requestable->respondible);

        $this->slideOver();

        $this->form([
            Tabs::make()
                ->contained(false)
                ->activeTab(3)
                ->schema([
                    Tab::make('Request')
                        ->schema(fn (Request $record) => [
                            ViewField::make('request')
                                ->view('filament.requests.schedule', ['schedule' => $record->requestable]),
                        ]),
                    Tab::make('History')
                        ->schema(fn (Request $record) => [
                            ViewField::make('request')
                                ->view('filament.requests.routing', ['schedule' => $record->requestable]),
                        ]),
                    Tab::make('Action')
                        ->schema([
                            Radio::make('status')
                                ->live()
                                ->label('Action')
                                ->validationAttribute('action')
                                ->required()
                                ->options(fn (Request $record) => [
                                    RequestStatus::APPROVE->value => 'Approve',
                                    RequestStatus::REJECT->value => 'Reject',
                                    RequestStatus::RETURN->value => 'Return',
                                    ...($record->final ? [RequestStatus::ESCALATE->value => 'Escalate'] : []),
                                    ...($record->requestable->deflectable ? [RequestStatus::DEFLECT->value => 'Deflect'] : []),
                                ])
                                ->descriptions([
                                    RequestStatus::APPROVE->value => 'Approve the request if everything is in order.',
                                    RequestStatus::REJECT->value => 'Reject the request if it is not allowed or not possible.',
                                    RequestStatus::RETURN->value => 'Return the request if it is invalid or needs to be revised.',
                                    RequestStatus::ESCALATE->value => 'Escalate the request if it needs further review or approval.',
                                    RequestStatus::DEFLECT->value => 'Deflect the request if it is not within your jurisdiction.',
                                ]),
                            Select::make('target')
                                ->visible(fn (Get $get) => $get('status') === RequestStatus::ESCALATE->value)
                                ->required()
                                ->options(fn (Request $record) => collect($record->requestable->route->escalation)
                                    ->mapWithKeys(fn ($target) => [$target => ucwords(settings($target) ?? $target)]),
                                ),
                            RichEditor::make('remarks')
                                ->hidden(fn (Get $get, Model $record) => $record->final && $get('status') === RequestStatus::APPROVE->value)
                                ->visible(fn (Get $get) => $get('status') !== null && $get('status') !== RequestStatus::APPROVE->value)
                                ->markAsRequired()
                                ->toolbarButtons([
                                    'bold',
                                    'italic',
                                    'underline',
                                    'strike',
                                    'bulletList',
                                    'orderedList',
                                    'link',
                                ]),
                        ]),
                    Tab::make('Threshold')
                        ->visible(fn (Get $get, Request $record) => $record->requestable instanceof Schedule ? $record->final && $get('status') === 'approved' : false)
                        ->schema($this->scheduleThresholdForm()),
                ]),
        ]);

        $this->action(function (Request $record, array $data) {
            DB::transaction(function () use ($data, $record) {
                if ($data['status'] === RequestStatus::ESCALATE->value) {
                    $record->requestable->requests()->create([
                        'status' => RequestStatus::ESCALATE,
                        'to' => $data['target'],
                        'step' => null,
                        'user_id' => Auth::id(),
                        'remarks' => $data['remarks'] ?? null,
                    ]);
                } elseif ($data['status'] === RequestStatus::DEFLECT->value) {
                    $record->requestable->requests()->create([
                        'status' => RequestStatus::DEFLECT,
                        'to' => $record->requestable->route->final(),
                        'step' => $record->requestable->route->final(true),
                        'user_id' => Auth::id(),
                        'remarks' => $data['remarks'] ?? null,
                    ]);
                } else {
                    $record->requestable->requests()->create([
                        'status' => $data['status'],
                        'user_id' => Auth::id(),
                        'to' => Filament::getCurrentOrDefaultPanel()->getId(),
                        'step' => $record->step,
                        'remarks' => $data['remarks'] ?? null,
                        'completed' => $data['status'] === RequestStatus::REJECT->value ||
                            $record->final && $data['status'] === RequestStatus::APPROVE->value,
                    ]);
                }

                if ($data['status'] === RequestStatus::APPROVE->value) {
                    if ($record->final) {
                        if ($record->requestable instanceof Schedule) {
                            $record->requestable->update([
                                'threshold' => $data['threshold'],
                            ]);
                        }
                    } elseif ($record->escalated) {
                        $record->requestable->requests()->create([
                            'status' => RequestStatus::REQUEST,
                            'to' => $record->requestable->route->final(),
                            'step' => $record->requestable->route->final(true),
                            'user_id' => null,
                        ]);
                    } elseif ($record->requestable->next_route) {
                        $record->requestable->requests()->create([
                            'status' => RequestStatus::REQUEST,
                            'to' => $record->requestable->next_route,
                            'step' => $record->step + 1,
                            'user_id' => null,
                        ]);
                    }
                }
            });
        });
    }

    protected function scheduleThresholdForm(): array
    {
        return [
            Fieldset::make('Punch 1')
                ->schema([
                    TextInput::make('threshold.p1.min')
                        ->label('Min')
                        ->hint('mins')
                        ->hintIcon('heroicon-m-question-mark-circle')
                        ->hintIconTooltip('Disregard attendance records that are "before" the specified number of minutes from Punch 1.')
                        ->default(fn (Request $record) => $record->requestable->arrangement == WorkArrangement::STANDARD_WORK_HOUR->value ? 280 : 120)
                        ->numeric()
                        ->type('text')
                        ->markAsRequired()
                        ->rules(['required', 'min:0']),
                    TextInput::make('threshold.p1.max')
                        ->label('Max')
                        ->hint('mins')
                        ->hintIcon('heroicon-m-question-mark-circle')
                        ->hintIconTooltip('Disregard attendance records that are "after" the specified number of minutes from Punch 1.')
                        ->default(fn (Request $record) => $record->requestable->arrangement == WorkArrangement::STANDARD_WORK_HOUR->value ? 180 : 360)
                        ->numeric()
                        ->type('text')
                        ->markAsRequired()
                        ->rules(['required', 'min:0']),
                ]),
            Fieldset::make('Punch 2')
                ->schema([
                    TextInput::make('threshold.p2.min')
                        ->label('Min')
                        ->hint('mins')
                        ->hintIcon('heroicon-m-question-mark-circle')
                        ->hintIconTooltip('Disregard attendance records that are "before" the specified number of minutes from Punch 2.')
                        ->default(fn (Request $record) => $record->requestable->arrangement == WorkArrangement::STANDARD_WORK_HOUR->value ? 180 : 360)
                        ->numeric()
                        ->type('text')
                        ->markAsRequired()
                        ->rules(['required', 'min:0']),
                    TextInput::make('threshold.p2.max')
                        ->label('Max')
                        ->hint('mins')
                        ->hintIcon('heroicon-m-question-mark-circle')
                        ->hintIconTooltip('Disregard attendance records that are "after" the specified number of minutes from Punch 2.')
                        ->default(fn (Request $record) => $record->requestable->arrangement == WorkArrangement::STANDARD_WORK_HOUR->value ? 120 : 420)
                        ->numeric()
                        ->type('text')
                        ->markAsRequired()
                        ->rules(['required', 'min:0']),
                ]),
            Fieldset::make('Punch 3')
                ->schema([
                    TextInput::make('threshold.p3.min')
                        ->label('Min')
                        ->hint('mins')
                        ->hintIcon('heroicon-m-question-mark-circle')
                        ->hintIconTooltip('Disregard attendance records that are "before" the specified number of minutes from Punch 3.')
                        ->default(120)
                        ->numeric()
                        ->type('text')
                        ->markAsRequired()
                        ->rules(['required', 'min:0']),
                    TextInput::make('threshold.p3.max')
                        ->label('Max')
                        ->hint('mins')
                        ->hintIcon('heroicon-m-question-mark-circle')
                        ->hintIconTooltip('Disregard attendance records that are "after" the specified number of minutes from Punch 3.')
                        ->default(180)
                        ->numeric()
                        ->type('text')
                        ->markAsRequired()
                        ->rules(['required', 'min:0']),
                ]),
            Fieldset::make('Punch 4')
                ->schema([
                    TextInput::make('threshold.p4.min')
                        ->label('Min')
                        ->hint('mins')
                        ->hintIcon('heroicon-m-question-mark-circle')
                        ->hintIconTooltip('Disregard attendance records that are "before" the specified number of minutes from Punch 4.')
                        ->default(180)
                        ->numeric()
                        ->markAsRequired()
                        ->type('text')
                        ->markAsRequired()
                        ->rules(['required', 'min:0']),
                    TextInput::make('threshold.p4.max')
                        ->label('Max')
                        ->hint('mins')
                        ->hintIcon('heroicon-m-question-mark-circle')
                        ->hintIconTooltip('Disregard attendance records that are "after" the specified number of minutes from Punch 4.')
                        ->default(360)
                        ->numeric()
                        ->type('text')
                        ->markAsRequired()
                        ->rules(['required', 'min:0']),
                ]),
        ];
    }
}
