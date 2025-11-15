<?php

namespace App\Filament\Legal\Pages;

use Filament\Pages\Page;

class UserAgreement extends Page
{
    protected static ?string $title = '';

    protected static ?string $navigationLabel = 'User Agreement';

    protected string $view = 'filament.legal.pages.index';

    public function agreement()
    {
        return settings('ua');
    }
}
