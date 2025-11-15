<?php

namespace App\Filament\Superuser\Pages;

use App\Models\Setting;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Notifications\Notification;
use Filament\Pages\Concerns\InteractsWithFormActions;
use Filament\Pages\Page;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Illuminate\Auth\Access\AuthorizationException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

use function Filament\authorize;

class Settings extends Page
{
    use InteractsWithFormActions;
    use InteractsWithForms;

    protected static ?int $navigationSort = PHP_INT_MAX;

    protected static string|\BackedEnum|null $navigationIcon = 'gmdi-tune-o';

    protected string $view = 'filament.superuser.pages.settings';

    protected ?string $subheading = 'This is global settings for the application.';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        try {
            return authorize('viewAny', Setting::class)->allowed();
        } catch (AuthorizationException $exception) {
            return $exception->toResponse()->allowed();
        }
    }

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
                Tabs::make()
                    ->tabs([
                        Tab::make('General Information')
                            ->columns(5)
                            ->schema([
                                FileUpload::make('seal')
                                    ->columnSpan(1)
                                    ->visibility('public')
                                    ->getUploadedFileNameForStorageUsing(fn (TemporaryUploadedFile $file) => 'seal.'.$file->extension())
                                    ->imageEditor()
                                    ->avatar()
                                    ->required()
                                    ->maxSize(2048),
                                Group::make([
                                    TextInput::make('name')
                                        ->markAsRequired()
                                        ->rule('required'),
                                    TextInput::make('address')
                                        ->markAsRequired()
                                        ->rule('required'),
                                    TextInput::make('url')
                                        ->url(),
                                    TextInput::make('email')
                                        ->rule('email'),
                                ])->columnSpan(2),
                            ]),
                        Tab::make('Privacy Policy')
                            ->schema([
                                MarkdownEditor::make('pp')
                                    ->hiddenLabel(),
                            ]),
                        Tab::make('User Agreement')
                            ->schema([
                                MarkdownEditor::make('ua')
                                    ->hiddenLabel(),
                            ]),
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
