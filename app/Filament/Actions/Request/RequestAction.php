<?php

namespace App\Filament\Actions\Request;

use App\Enums\RequestStatus;
use App\Models\Route;
use App\Models\Schedule;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithRecord;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class RequestAction extends Action
{
    use InteractsWithRecord;

    protected ?Route $route = null;

    protected ?Closure $validation = null;

    protected ?string $type = null;

    protected string|Closure|null $failureNotificationBody = null;

    protected string|Closure|null $successNotificationBody = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->name ??= 'request';

        $this->requiresConfirmation();

        $this->modalWidth('2xl');

        $this->label('Request');

        $this->failureNotificationTitle('Request failed');

        $this->failureNotificationBody('Please check the request again.');

        $this->successNotificationTitle('Request success');

        $this->slideOver();

        $this->visible(settings('requests'));

        $this->modalDescription(<<<'DESC'
            Be sure to finalize everything else before proceeding as you will not be able to make adjustments after this action.
            Would you like to continue?
        DESC);

        $this->successNotificationBody(function () {
            $target = $this->record->next_route;

            $alias = settings($target, true) ?? $target;

            return "Request has been forwarded to the {$alias}. Please wait patiently for their response.";
        });

        $this->schema([
            TextInput::make('title')
                ->markAsRequired()
                ->default($this->record->title)
                ->rule('required')
                ->rule(fn () => function ($a, $v, $f) {
                    if (str($v)->startsWith('@')) {
                        $f('The title should not start with @.');
                    }
                }),
            RichEditor::make('body')
                ->required()
                ->toolbarButtons([
                    'bold',
                    'italic',
                    'underline',
                    'strike',
                    'bulletList',
                    'orderedList',
                    'link',
                ])
                ->default($this->record->application?->body),
            RichEditor::make('remarks')
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
        ]);

        $this->action(function (array $data) {
            if ($this->validation && ! $this->evaluate($this->validation)) {
                $this->sendFailureNotification();

                $this->halt();
            }

            DB::transaction(function () use ($data) {
                $this->record->forceFill([
                    'title' => $data['title'],
                    'requestor_id' => Auth::id(),
                    'requested_at' => now(),
                ])->save();

                $this->record->requests()->create([
                    'title' => $data['title'],
                    'body' => $data['body'],
                    'status' => RequestStatus::REQUEST,
                    'to' => $this->record->next_route,
                    'user_id' => Auth::id(),
                    'remarks' => $data['remarks'],
                    'step' => 1,
                ]);
            });

            $this->redirect(route('filament.secretary.resources.schedules.index'));

            $this->sendSuccessNotification();
        });
    }

    public function type(string $type): static
    {
        $this->type = $type;

        if (! in_array($type, [Schedule::class])) {
            throw new InvalidArgumentException("Can not send requests to route {$type}");
        }

        $this->route = Route::whereModel($type)->first();

        if (is_null($this->route)) {
            throw new InvalidArgumentException("Route for {$type} not found. Please contact the system administrators.");
        }

        return $this;
    }

    public function validation(Closure $validation): static
    {
        $this->validation = $validation;

        return $this;
    }

    public function sendSuccessNotification(): static
    {
        $notification = $this->evaluate($this->successNotification, [
            'notification' => Notification::make()
                ->success()
                ->title($this->getSuccessNotificationTitle())
                ->body($this->getSuccessNotificationBody()),
        ]);

        if (filled($notification?->getTitle())) {
            $notification->send();
        }

        return $this;
    }

    public function sendFailureNotification(): static
    {
        $notification = $this->evaluate($this->failureNotification, [
            'notification' => Notification::make()
                ->danger()
                ->title($this->getFailureNotificationTitle())
                ->body($this->getFailureNotificationBody()),
        ]);

        if (filled($notification?->getTitle())) {
            $notification->send();
        }

        return $this;
    }

    public function successNotificationBody(string|Closure|null $body): static
    {
        $this->successNotificationBody = $body;

        return $this;
    }

    public function failureNotificationBody(string|Closure|null $body): static
    {
        $this->failureNotificationBody = $body;

        return $this;
    }

    public function getSuccessNotificationBody(): ?string
    {
        return $this->evaluate($this->successNotificationBody);
    }

    public function getFailureNotificationBody(): ?string
    {
        return $this->evaluate($this->failureNotificationBody);
    }
}
