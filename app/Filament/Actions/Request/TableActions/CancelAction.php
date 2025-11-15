<?php

namespace App\Filament\Actions\Request\TableActions;

use App\Enums\RequestStatus;
use Filament\Actions\Action;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\TextInput;
use Illuminate\Database\Eloquent\Model;

class CancelAction extends Action
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->name ??= 'cancel-request';

        $this->groupedIcon('gmdi-cancel-o');

        $this->hidden(fn (Model $record) => $record->drafted);

        $this->disabled(fn (Model $record) => ! $record->cancellable);

        $this->form([
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
            TextInput::make('password')
                ->password()
                ->markAsRequired()
                ->rule('required')
                ->currentPassword(),
        ]);

        $this->requiresConfirmation();

        $this->modalDescription('Are you sure you want to cancel this request? Please enter your password to continue.');

        $this->successNotificationTitle('Request has been cancelled');

        $this->action(function (Model $record, array $data) {
            $record->forceFill([
                'requestor_id' => null,
                'requested_at' => null,
            ])->save();

            $record->requests()->create([
                'status' => RequestStatus::CANCEL,
                'user_id' => auth()->id(),
                'remarks' => $data['remarks'] ?? null,
            ]);

            $this->sendSuccessNotification();
        });
    }

    protected function endPoint() {}
}
