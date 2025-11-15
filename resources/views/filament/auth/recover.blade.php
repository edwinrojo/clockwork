@extends('filament.auth.base')

@section('content')
    {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::AUTH_PASSWORD_RESET_RESET_FORM_BEFORE, scopes: $this->getRenderHookScopes()) }}

    <form wire:submit="resetPassword">
        {{ $this->form }}

        <x-filament::actions
            :actions="$this->getFormActions()"
            class="mt-4"
        />
    </form>

    {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::AUTH_PASSWORD_RESET_RESET_FORM_AFTER, scopes: $this->getRenderHookScopes()) }}
@endsection

