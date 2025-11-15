@extends('filament.auth.base')

@section('subheading')
    {{ $this->homeAction }}
@endsection

@section('content')
    {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::AUTH_LOGIN_FORM_BEFORE, scopes: $this->getRenderHookScopes()) }}

    <x-filament-panels::form wire:submit="authenticate">
        {{ $this->form }}

        <x-filament::actions
            :actions="$this->getFormActions()"
        />
    </x-filament-panels::form>

    @if ($providers = config('services.oauth_providers'))
        <div class="relative flex items-center justify-center text-center">
            <div class="absolute w-[calc(100%-2rem)] h-px border-t border-gray-200 mr-8"></div>
            <p class="relative tracking-tight inline-flex items-center p-2 px-4 text-sm font-medium text-gray-500 bg-white rounded-full dark:bg-gray-700 dark:text-gray-100">
                Or log in via
            </p>
            <div class="absolute w-full justify-items-end">
                <svg
                    style="--c-400:var(--gray-400);--c-500:var(--gray-500);"
                    class="w-5 h-5 ml-2 text-gray-400 fi-fo-field-wrp-hint-icon dark:text-gray-500"
                    xmlns="http://www.w3.org/2000/svg"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke-width="1.5"
                    stroke="currentColor"
                    aria-hidden="true"
                    data-slot="icon"
                    x-data="{}"
                    x-tooltip="{
                        content: 'Ensure your email matches the one registered in the system and the selected provider.',
                        theme: $store.theme,
                    }"
                >
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 5.25h.008v.008H12v-.008Z"></path>
                </svg>
            </div>
        </div>

        <div class="grid gap-3 sm:grid-cols-2">
            @foreach ($providers as $provider)
                <x-filament::button
                    :icon="'fab-'.$provider"
                    color="gray"
                    outlined
                    wire:click="socialite('{{ $provider }}')"
                    wire:target="socialite('{{ $provider }}'"
                >
                    {{ ucfirst($provider) }}
                </x-filament::button>
            @endforeach
        </div>
    @endif

    {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::AUTH_LOGIN_FORM_AFTER, scopes: $this->getRenderHookScopes()) }}
@endsection

@section('footer')
    @php($ua = settings('ua'))

    @php($pp = settings('pp'))

    @if ($ua || $pp)
        <div class="text-sm text-center text-gray-500">
            <p>
                By continuing, you agree to our
                @if ($pp)
                    <x-filament::link href="{{ route('filament.legal.pages.privacy-policy') }}">
                        Privacy Policy
                    </x-filament::link>
                @endif

                @if ($ua && $pp)
                    &amp;
                @endif

                @if ($ua)
                    <x-filament::link href="{{ route('filament.legal.pages.user-agreement') }}">
                        User Agreement
                    </x-filament::link>
                @endif
            </p>
        </div>
    @endif
@endsection
