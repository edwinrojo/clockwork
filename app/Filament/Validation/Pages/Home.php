<?php

namespace App\Filament\Validation\Pages;

use App\Models\Export;
use Filament\Actions\Action;
use Filament\Infolists\Components\TextEntry;
use Filament\Pages\Dashboard;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontFamily;
use Filament\Support\Enums\Width;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Http\Request;
use Illuminate\View\View;

class Home extends Dashboard
{
    public ?Export $export = null;

    public ?string $url = null;

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.validation.pages.home';

    protected static ?string $navigationLabel = 'Home';

    protected ?string $heading = 'Validation';

    public function mount(Request $request): void
    {
        $this->export = Export::find($request->get('q'));
    }

    public function getMaxContentWidth(): Width
    {
        return Width::TwoExtraLarge;
    }

    public function getHeading(): string|Htmlable
    {
        return is_null($this->export) ? '' : 'Validation';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return @$this->export->exportable?->employee?->name;
    }

    public function getHeaderActions(): array
    {
        return is_null($this->export) ? [] : [
            Action::make('go_home')
                ->url('/'),
            Action::make('download_file')
                ->color('gray')
                ->action(fn () => response()->streamDownload(fn () => print ($this->export->content), pathinfo($this->export->filename, PATHINFO_BASENAME))),
        ];
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->record($this->export)
            ->columns(3)
            ->schema([
                Group::make([
                    TextEntry::make('exportable.days')
                        ->label('Days'),
                    TextEntry::make('overtime')
                        ->label('Overtime')
                        ->state(function ($record) {
                            return $record->exportable->getOvertime(true);
                        }),
                    TextEntry::make('undertime')
                        ->label('Undertime')
                        ->state(function ($record) {
                            return $record->exportable->getUndertime(true);
                        }),
                    TextEntry::make('missed')
                        ->label('Missed')
                        ->state(function ($record) {
                            return $record->exportable->getMissed(true);
                        }),
                ])
                    ->columns(2)
                    ->columnSpan('full'),
                TextEntry::make('created_at')
                    ->label('Certified by employee')
                    ->since()
                    ->dateTimeTooltip(),
                TextEntry::make('exportable.leaderSigner.created_at')
                    ->label('Verified by supervisor')
                    ->placeholder(str('<i>None</i>')->toHtmlString())
                    ->since()
                    ->dateTimeTooltip(),
                TextEntry::make('exportable.directorSigner.created_at')
                    ->label('Verified by head')
                    ->placeholder(str('<i>None</i>')->toHtmlString())
                    ->since()
                    ->dateTimeTooltip(),
                Group::make([
                    TextEntry::make('exportable')
                        ->label('Timesheet')
                        ->columnSpan('full')
                        ->formatStateUsing(function (): View {
                            return view('filament.validation.pages.csc', [
                                'timesheets' => [$this->export->exportable->setSpan($this->export->exportable->span)],
                                'styles' => false,
                                'month' => false,
                            ]);
                        }),
                    TextEntry::make('digest')
                        ->columnSpan('full')
                        ->fontFamily(FontFamily::Mono)
                        ->extraAttributes(['style' => 'word-break:break-all;'])
                        ->copyable()
                        ->copyMessage('Copied!')
                        ->copyMessageDuration(1500),
                ])->columnSpan('full'),
            ]);
    }
}
