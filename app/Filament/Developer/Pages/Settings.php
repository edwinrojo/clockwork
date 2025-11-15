<?php

namespace App\Filament\Developer\Pages;

use App\Enums\UserRole;
use App\Models\Setting;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Notifications\Notification;
use Filament\Pages\Concerns\InteractsWithFormActions;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class Settings extends Page
{
    use InteractsWithFormActions;
    use InteractsWithForms;

    protected static ?int $navigationSort = PHP_INT_MAX;

    protected static string|\BackedEnum|null $navigationIcon = 'gmdi-tune-o';

    protected string $view = 'filament.superuser.pages.settings';

    protected ?string $subheading = 'This is global settings for the application.';

    public ?array $data = [];

    public function mount(): void
    {
        $data = Setting::fetch()->toArray();

        $this->form->fill($data);
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('update')
                ->submit('save')
                ->keyBindings(['mod+s']),
        ];
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->schema([
                Section::make('Role Aliases')
                    ->columns(5)
                    ->schema(
                        collect(UserRole::cases())
                            ->reject(fn (UserRole $role) => in_array($role, [
                                UserRole::ROOT,
                                UserRole::NONE,
                            ]))
                            ->map(function (UserRole $role) {
                                return TextInput::make(mb_strtolower($role->getLabel(false)))
                                    ->columnSpan(2)
                                    ->label($role->getLabel(false))
                                    ->placeholder($role->getLabel(false))
                                    ->dehydrateStateUsing(fn (?string $state) => mb_strtolower($state) ?: null)
                                    ->regex('/^[a-zA-Z0-9\s]+$/');
                            })
                            ->toArray(),
                    ),
                Section::make('Features')
                    ->schema([
                        Toggle::make('requests')
                            ->helperText('Enable requests feature'),
                        Toggle::make('schedule')
                            ->helperText('Enable schedule feature'),
                    ]),
            ]);
    }

    public function save()
    {
        $data = collect($this->form->getState())->map(fn ($value, $key) => ['key' => $key, 'value' => $value]);

        Setting::set($data->values()->toArray());

        Notification::make()
            ->success()
            ->title('Settings updated')
            ->body('Changes have been saved.')
            ->send();
    }
}
