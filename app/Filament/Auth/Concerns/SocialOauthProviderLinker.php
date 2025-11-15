<?php

namespace App\Filament\Auth\Concerns;

use App\Models\Social;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs\Tab;

trait SocialOauthProviderLinker
{
    public ?string $currentUrl = null;

    public function mountSocialOauthProviderLinker()
    {
        $this->currentUrl = url()->current();
    }

    protected function socialFormTab(): Tab
    {
        return Tab::make('Socials')
            ->hidden(empty(config('services.oauth_providers')))
            ->schema([
                Section::make()
                    ->schema($this->getOathProviders()),
            ]);
    }

    protected function getOathProviders(): array
    {
        $socials = $this->getUser()->socials;

        return collect(config('services.oauth_providers'))->map(function (string $provider) use ($socials) {
            $email = $this->getUser()->email;

            $social = $socials?->first(fn ($social) => $social->provider === $provider);

            $unlink = <<<HTML
                After unlinking your $provider account,
                you will still be able log in with it
                as long as the email address used matches your account email
                <i>({$email})</i>.

                <br>

                <span class="mt-4 block text-gray-500">
                    If you want to completely remove this application's access to your $provider account,
                    you will need to revoke access from your $provider account settings.
                </span>
            HTML;

            $link = <<<HTML
                Link your $provider account to this account to enable single sign-on.
            HTML;

            return TextInput::make("socialite-$provider")
                ->label(ucfirst($provider))
                ->prefixIcon("fab-$provider")
                ->readOnly()
                ->dehydrated(false)
                ->disabled()
                ->hintActions([
                    Action::make("link-$provider")
                        ->visible($social === null)
                        ->label('Link')
                        ->icon('gmdi-link-o')
                        ->color('primary')
                        ->requiresConfirmation()
                        ->modalIcon('gmdi-link-o')
                        ->modalHeading(str('<i></i>')->toHtmlString())
                        ->modalDescription(str($link)->toHtmlString())
                        ->modalSubmitActionLabel('Continue')
                        ->action(fn () => $this->linkSocialAccount($provider)),
                    Action::make("unlink-$provider")
                        ->visible($social !== null)
                        ->label('Unlink')
                        ->icon('gmdi-link-off-o')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalIcon('gmdi-link-off-o')
                        ->modalHeading(str('<i></i>')->toHtmlString())
                        ->modalDescription(str($unlink)->toHtmlString())
                        ->modalSubmitActionLabel('Unlink')
                        ->action(fn () => $this->unlinkSocialAccount($social)),
                ]);
        })->toArray();
    }

    protected function linkSocialAccount(string $provider)
    {
        return redirect()->route('socialite.filament.auth.oauth.redirect', [
            'link' => true,
            'url' => $this->currentUrl.'/?tab=-socials-tab',
            'provider' => $provider,
            'guard' => match (Filament::getCurrentOrDefaultPanel()->getId()) {
                'employee' => 'employee',
                default => 'web',
            },
        ]);
    }

    protected function unlinkSocialAccount(Social $social): void
    {
        $social->delete();

        $this->form->fill([
            "socialite-{$social->provider}" => null,
        ]);

        Notification::make()
            ->title('Social account unlinked')
            ->body("Your {$social->provider} account has been unlinked.")
            ->success()
            ->send();
    }
}
