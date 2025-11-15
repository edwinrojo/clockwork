<?php

namespace App\Filament\Auth;

use App\Models\Employee;
use App\Models\User;
use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use Exception;
use Filament\Auth\Notifications\ResetPassword;
use Filament\Auth\Pages\PasswordReset\RequestPasswordReset;
use Filament\Facades\Filament;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Password;

class Reset extends RequestPasswordReset
{
    protected static string $layout = 'filament-panels::components.layout.base';

    protected string $view = 'filament.auth.reset';

    public function request(): void
    {
        try {
            $this->rateLimit(2);
        } catch (TooManyRequestsException $exception) {
            $this->getRateLimitedNotification($exception)?->send();

            return;
        }

        $data = $this->form->getState();

        $status = Password::broker($data['account_type'])->sendResetLink(
            ['email' => $data['email']],
            function (CanResetPassword $user, string $token) use ($data): void {
                if (! method_exists($user, 'notify')) {
                    $userClass = $user::class;

                    throw new Exception("Model [{$userClass}] does not have a [notify()] method.");
                }

                $notification = new ResetPassword($token);

                $notification->url = Filament::getResetPasswordUrl($token, $user, ['type' => $data['account_type']]);

                /** @var User|Employee $user */
                $user->notify($notification);
            },
        );

        if ($status !== Password::RESET_LINK_SENT) {
            Notification::make()
                ->title(__($status))
                ->danger()
                ->send();

            return;
        }

        Notification::make()
            ->title(__($status))
            ->success()
            ->send();

        $this->form->fill();
    }

    public function getHeading(): string|Htmlable
    {
        return 'Reset Password';
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                $this->getTypeFormComponent(),
                $this->getEmailFormComponent(),
            ]);
    }

    protected function getTypeFormComponent(): Component
    {
        return Radio::make('account_type')
            ->inline()
            ->inlineLabel(false)
            ->live()
            ->default('users')
            ->required()
            ->options([
                'users' => 'Administrator',
                'employees' => 'Employee',
            ]);
    }

    protected function getEmailFormComponent(): Component
    {
        return TextInput::make('email')
            ->label('Email')
            ->rules(['required', 'email'])
            ->markAsRequired()
            ->autofocus();
    }
}
