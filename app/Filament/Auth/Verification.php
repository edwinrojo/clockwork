<?php

namespace App\Filament\Auth;

use App\Models\Employee;
use App\Models\User;
use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use DanHarrin\LivewireRateLimiting\WithRateLimiting;
use Exception;
use Filament\Actions\Action;
use Filament\Auth\Notifications\VerifyEmail;
use Filament\Facades\Filament;
use Filament\Facades\Filament\Schemas\Schema;
use Filament\Notifications\Notification;
use Filament\Pages\SimplePage;
use Filament\Panel;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Contracts\Support\Htmlable;

/**
 * @property Schema $form
 */
class Verification extends SimplePage
{
    use WithRateLimiting;

    /**
     * @var view-string
     */
    protected string $view = 'filament-panels::pages.auth.email-verification.email-verification-prompt';

    public function mount(): void
    {
        $panel = $this->getPanel();

        $user = $this->getVerifiable();

        if ($user === null) {
            match ($panel->getId()) {
                'employee' => redirect(url('/')),
                default => redirect()->route('filament.employee.resources.timesheets.index'),
            };

            return;
        }

        if ($user->hasVerifiedEmail()) {
            redirect()->intended(Filament::getUrl());
        }
    }

    protected function getVerifiable(): ?MustVerifyEmail
    {
        /** @var MustVerifyEmail */
        $user = Filament::auth()->user();

        return $user;
    }

    protected function getPanel()
    {
        /** @var Panel */
        $panel = Filament::getCurrentOrDefaultPanel();

        return $panel;
    }

    protected function sendEmailVerificationNotification(MustVerifyEmail|User|Employee $user): void
    {
        if ($user->hasVerifiedEmail()) {
            return;
        }

        if (! method_exists($user, 'notify')) {
            $userClass = $user::class;

            throw new Exception("Model [{$userClass}] does not have a [notify()] method.");
        }

        $notification = app(VerifyEmail::class);
        $notification->url = Filament::getVerifyEmailUrl($user);

        $user->notify($notification);
    }

    public function resendNotificationAction(): Action
    {
        return Action::make('resendNotification')
            ->link()
            ->label('Resend notification')
            ->size('sm')
            ->action(function (): void {
                try {
                    $this->rateLimit(2);
                } catch (TooManyRequestsException $exception) {
                    $this->getRateLimitedNotification($exception)?->send();

                    return;
                }

                $this->sendEmailVerificationNotification($this->getVerifiable());

                Notification::make()
                    ->title(__('filament-panels::pages/auth/email-verification/email-verification-prompt.notifications.notification_resent.title'))
                    ->success()
                    ->send();
            });
    }

    protected function getRateLimitedNotification(TooManyRequestsException $exception): ?Notification
    {
        return Notification::make()
            ->title(__('filament-panels::pages/auth/email-verification/email-verification-prompt.notifications.notification_resend_throttled.title', [
                'seconds' => $exception->secondsUntilAvailable,
                'minutes' => $exception->minutesUntilAvailable,
            ]))
            ->body(array_key_exists('body', __('filament-panels::pages/auth/email-verification/email-verification-prompt.notifications.notification_resend_throttled') ?: []) ? __('filament-panels::pages/auth/email-verification/email-verification-prompt.notifications.notification_resend_throttled.body', [
                'seconds' => $exception->secondsUntilAvailable,
                'minutes' => $exception->minutesUntilAvailable,
            ]) : null)
            ->danger();
    }

    public function getTitle(): string|Htmlable
    {
        return 'Email Verification';
    }

    public function getHeading(): string|Htmlable
    {
        return 'Verify Your Email';
    }
}
