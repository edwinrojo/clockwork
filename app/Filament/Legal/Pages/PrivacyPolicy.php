<?php

namespace App\Filament\Legal\Pages;

use Filament\Pages\Page;

class PrivacyPolicy extends Page
{
    protected static ?string $title = '';

    protected static ?string $navigationLabel = 'Privacy Policy';

    protected string $view = 'filament.legal.pages.index';

    public function agreement()
    {
        return settings('pp');
    }
}
