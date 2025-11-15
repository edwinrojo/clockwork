<?php

namespace App\Filament\Auth;

use App\Http\Responses\LoginResponse;
use App\Models\Employee;
use App\Models\Office;
use App\Models\User;
use App\Traits\CanSendEmailVerification;
use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Models\Contracts\FilamentUser;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Support\Facades\FilamentIcon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Http\Request as HttpRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class Login extends \Filament\Auth\Pages\Login
{
    use CanSendEmailVerification;

    public array $verification = [];

    protected static string $layout = 'filament-panels::components.layout.base';

    protected string $view = 'filament.auth.login';

    public function mount(): void
    {
        if (Filament::auth()->check()) {
            redirect()->intended(Filament::getUrl());
        }

        if (Auth::guard('employee')->check()) {
            redirect()->route('filament.employee.resources.timesheets.index');
        }

        if (session()->has('url.intended')) {
            $previous = Route::getRoutes()->match(HttpRequest::create(session('url.intended')));

            if (strpos($previous->action['prefix'] ?? '', 'email-verification') !== false) {
                $this->verification = [
                    'id' => $id = @$previous->parameters['id'],
                    'guard' => $guard = match (strpos($previous->action['prefix'], 'employee') !== false) {
                        true => 'employee',
                        default => 'web',
                    },
                    'model' => $model = match ($guard) {
                        'employee' => Employee::class,
                        default => User::class,
                    },
                    'email' => $model::find($id)->email,
                ];
            }
        }

        $this->form->fill();
    }

    public function getHeading(): string|Htmlable
    {
        return empty($this->verification)
            ? 'Sign in'
            : 'Sign in to verify your email address';
    }

    public function homeAction(): Action
    {
        return Action::make('go-home')
            ->link()
            ->label('back to home')
            ->icon(match (__('filament-panels::layout.direction')) {
                'rtl' => FilamentIcon::resolve('panels::pages.password-reset.request-password-reset.actions.login.rtl') ?? 'heroicon-m-arrow-right',
                default => FilamentIcon::resolve('panels::pages.password-reset.request-password-reset.actions.login') ?? 'heroicon-m-arrow-left',
            })
            ->url('/');
    }

    public function authenticate(): ?LoginResponse
    {
        try {
            $this->rateLimit(5);
        } catch (TooManyRequestsException $exception) {
            $this->getRateLimitedNotification($exception)?->send();

            return null;
        }

        $data = $this->form->getState();

        if (
            ! Auth::guard($data['login_as'])
                ->attempt($this->getCredentialsFromFormData($data), $data['remember'] ?? false)
        ) {
            $this->throwFailureValidationException();
        }

        $user = Filament::auth()->user();

        if (
            ($user instanceof FilamentUser) &&
            (! $user->canAccessPanel(Filament::getCurrentOrDefaultPanel()))
        ) {
            Filament::auth()->logout();

            $this->throwFailureValidationException();
        }

        session()->regenerate();

        return app(LoginResponse::class);
    }

    public function socialite(string $provider)
    {
        try {
            $this->rateLimit(5);
        } catch (TooManyRequestsException $exception) {
            $this->getRateLimitedNotification($exception)?->send();

            return null;
        }

        $guard = match ($this->form->getRawState()['login_as'] ?? null) {
            'employee' => 'employee',
            default => 'web',
        };

        return redirect()->route('socialite.filament.auth.oauth.redirect', [
            'provider' => $provider, 'guard' => $guard,
        ]);
    }

    protected function getFormActions(): array
    {
        return [
            $this->removeVerificationIntent(),
            $this->getAuthenticateFormAction(),
            ActionGroup::make([
                $this->getSocialiteLoginFormAction('google'),
                $this->getSocialiteLoginFormAction('microsoft'),
            ])
                ->hidden()
                ->button()
                ->label('More options')
                ->color('gray'),
        ];
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            $this->getAuthenticationOptionFormComponent(),
            $this->getEmailFormComponent(),
            $this->getPasswordFormComponent(),
            $this->getRememberFormComponent(),
        ]);
    }

    protected function getSocialiteLoginFormAction(string $provider): Action
    {
        return Action::make($provider)
            ->icon("fab-$provider")
            ->action("socialite('$provider')");
    }

    protected function getAuthenticationOptionFormComponent()
    {
        return Radio::make('login_as')
            ->inline()
            ->live()
            ->default(fn () => $this->verification['guard'] ?? session()->get('guard') ?? 'web')
            ->hidden(fn () => $this->verification['guard'] ?? false)
            ->dehydratedWhenHidden()
            ->required()
            ->options([
                'web' => 'Administrator',
                'employee' => 'Employee',
            ])
            ->extraInputAttributes(['tabindex' => 2]);
    }

    protected function removeVerificationIntent(): Action
    {
        return Action::make('remove-verification-intent')
            ->label('Cancel')
            ->hidden(empty($this->verification))
            ->color('gray')
            ->action(function () {
                session()->forget('url.intended');

                return redirect()->route('filament.auth.auth.login');
            });
    }

    protected function getEmailFormComponent(): Component
    {
        return TextInput::make('username')
            ->label('Username')
            ->required(false)
            ->markAsRequired()
            ->rule('required')
            ->autocomplete()
            ->autofocus()
            ->default(fn () => $this->verification['email'] ?? null)
            ->label(fn (Get $get) => $get('login_as') === 'web' ? 'Username' : 'Email')
            ->rule(fn (Get $get) => $get('login_as') !== 'web' ? 'email' : null)
            ->hintAction($this->getAccountSetupAction())
            ->extraInputAttributes(['tabindex' => 3]);
    }

    protected function getPasswordFormComponent(): Component
    {
        return parent::getPasswordFormComponent()
            ->required(false)
            ->markAsRequired()
            ->rule('required')
            ->hint(filament()->hasPasswordReset() && empty($this->verification) ? new HtmlString(Blade::render('<x-filament::link :href="filament()->getRequestPasswordResetUrl()" tabindex="6"> Forgot password?</x-filament::link>')) : null)
            ->extraInputAttributes(['tabindex' => 5]);
    }

    protected function getRememberFormComponent(): Component
    {
        return parent::getRememberFormComponent()
            ->extraAttributes(['tabindex' => 7]);
    }

    protected function getAccountSetupAction(): Action
    {
        $lacking = function ($employee, $fail = null, $message = '', $boolean = true) {
            $employee = $employee instanceof Employee ? $employee : Employee::find($employee);

            $lacking = $employee?->offices->isEmpty() || empty($employee?->birthdate) || empty($employee?->sex);

            if ($lacking && $boolean) {
                return true;
            }

            if ($lacking && $fail && $message && $boolean === false) {
                return $fail($message);
            }
        };

        $confirmation = function ($data, $fail, $success = false) {
            if ($data[0] !== null && (is_null($data[1]) || is_null($data[2]) || is_null($data[3]))) {
                return false;
            }

            $employee = Employee::find($data[0]);

            if (is_null($fail) && is_null($employee)) {
                return false;
            }

            if (is_null($employee)) {
                $fail('Employee not found.');
            }

            if (
                ! $employee?->birthdate?->isSameDay($data[1] ?? '') ||
                ! $employee?->offices->pluck('id')->contains($data[3]) ||
                $employee?->sex !== $data[2]
            ) {
                return $fail('Data does not match the record.');
            }

            if (! empty($employee->password) && ! empty($employee->email_verified_at) && $success) {
                return $fail('Employee account has already been set up.');
            }
        };

        $action = Action::make('Setup Account')
            ->visible(empty($this->verification))
            ->successNotificationTitle('Account setup successful')
            ->requiresConfirmation()
            ->modalHeading('Setup Account')
            ->modalDescription(null)
            ->modalWidth('xl')
            ->modalIcon('heroicon-o-shield-check')
            ->modalCancelActionLabel('Cancel')
            ->closeModalByClickingAway(false)
            ->extraAttributes(['tabindex' => 4])
            ->slideOver()
            ->disabled(fn (Get $get) => $get('login_as') !== 'employee')
            ->schema([
                Wizard::make([
                    Step::make('Confirmation')
                        ->description('Confirm your identity')
                        ->schema([
                            Select::make('employee')
                                ->label('Employee')
                                ->placeholder('Select employee')
                                ->searchable()
                                ->required()
                                ->reactive()
                                ->afterStateUpdated(fn (Set $set) => $set('password', null))
                                ->getSearchResultsUsing(function (string $search) {
                                    return Employee::query()
                                        ->where('name', 'ilike', "%$search%")
                                        ->orWhere('full_name', 'ilike', "%$search%")
                                        ->pluck('name', 'id')
                                        ->take(5);
                                })
                                ->rule(fn (Get $get) => function ($a, $v, $f) use ($get, $confirmation, $lacking) {
                                    if ($get('birthdate') !== null && $get('sex') !== null && $get('office') !== null) {
                                        $message = 'Employee data is incomplete. Please contact the officers in charge.';

                                        $lacking($v, $f, $message, false);

                                        $confirmation([$v, $get('birthdate'), $get('sex'), $get('office')], $f, 1);
                                    }
                                }),
                            DatePicker::make('birthdate')
                                ->required()
                                ->rule('date')
                                ->rule(fn (Get $get) => function ($a, $v, $f) use ($get, $confirmation, $lacking) {
                                    if ($get('birthdate') !== null && $get('sex') !== null && $get('office') !== null && ! $lacking($get('employee'), boolean: true)) {
                                        $confirmation([$get('employee'), $get('birthdate'), $get('sex'), $get('office')], $f);
                                    }
                                }),
                            Select::make('sex')
                                ->required()
                                ->in(['male', 'female'])
                                ->options([
                                    'male' => 'Male',
                                    'female' => 'Female',
                                ])
                                ->rule(fn (Get $get) => function ($a, $v, $f) use ($get, $confirmation, $lacking) {
                                    if ($get('birthdate') !== null && $get('sex') !== null && $get('office') !== null && ! $lacking($get('employee'), boolean: true)) {
                                        $confirmation([$get('employee'), $get('birthdate'), $get('sex'), $get('office')], $f);
                                    }
                                }),
                            Select::make('office')
                                ->required()
                                ->searchable()
                                ->getSearchResultsUsing(function (string $search) {
                                    return Office::query()
                                        ->where('name', 'ilike', "%$search%")
                                        ->orWhere('code', 'ilike', "%$search%")
                                        ->pluck('code', 'id')
                                        ->take(5);
                                })
                                ->rule(fn (Get $get) => function ($a, $v, $f) use ($get, $confirmation, $lacking) {
                                    if ($get('birthdate') !== null && $get('sex') !== null && $get('office') !== null && ! $lacking($get('employee'), boolean: true)) {
                                        $confirmation([$get('employee'), $get('birthdate'), $get('sex'), $get('office')], $f);
                                    }
                                }),
                        ]),
                    Step::make('Authentication')
                        ->description('Set your login credentials')
                        ->schema([
                            TextInput::make('email')
                                ->markAsRequired()
                                ->rule(fn (Get $get) => Rule::unique('employees', 'email')->ignore($get('employee')))
                                ->rules(['required', 'email:strict,dns,spoof,filter']),
                            TextInput::make('password')
                                ->label('New Password')
                                ->validationAttribute('new password')
                                ->password()
                                ->visible(fn (Get $get) => Employee::find($get('employee'))?->password === null)
                                ->same('password_confirmation')
                                ->markAsRequired()
                                ->rule('required')
                                ->live()
                                ->rule(Password::default()->uncompromised()),
                            TextInput::make('password_confirmation')
                                ->label('Confirm password')
                                ->password()
                                ->visible(fn (Get $get) => Employee::find($get('employee'))?->password === null)
                                ->markAsRequired()
                                ->rule('required'),
                        ]),
                ]),
            ])
            ->action(function (Action $action, array $data) {
                $employee = Employee::find($data['employee']);

                $data = array_filter(['email' => $data['email'], 'password' => $data['password'] ?? null]);

                $employee->update($data);

                $this->sendEmailVerificationNotification($employee);

                $action->sendSuccessNotification();
            });

        return $action;
    }

    protected function throwFailureValidationException(): never
    {
        throw ValidationException::withMessages([
            'data.username' => __('filament-panels::pages/auth/login.messages.failed'),
        ]);
    }

    protected function getCredentialsFromFormData(array $data): array
    {
        return [
            filter_var($data['username'], FILTER_VALIDATE_EMAIL) ? 'email' : 'username' => $data['username'],
            'password' => $data['password'],
        ];
    }
}
