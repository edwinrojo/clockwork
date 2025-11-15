<?php

namespace App\Filament\Secretary\Resources\GroupResource\Pages;

use App\Filament\Secretary\Resources\GroupResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Support\Htmlable;

class ListGroups extends ListRecords
{
    protected static string $resource = GroupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    public function getSubheading(): string|Htmlable|null
    {
        // $subheading = <<<'HTML'
        //     <span class="text-sm text-custom-600 dark:text-custom-400" style="--c-400:var(--warning-400);--c-600:var(--warning-600);">
        //         Groups are accessible to all users along with its members.
        //     </span>
        // HTML;

        // return str($subheading)->toHtmlString();

        return null;
    }
}
