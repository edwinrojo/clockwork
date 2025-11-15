@extends('filament.auth.base')

@section('subheading')
    {{ $this->loginAction }}
@endsection

@section('content')
    {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::AUTH_PASSWORD_RESET_REQUEST_FORM_BEFORE, scopes: $this->getRenderHookScopes()) }}

    <form wire:submit="request">
        {{ $this->form }}

        <x-filament::actions
            :actions="$this->getFormActions()"
            class="mt-4"
        />
    </form>

    {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::AUTH_PASSWORD_RESET_REQUEST_FORM_AFTER, scopes: $this->getRenderHookScopes()) }}
@endsection
