@extends('filament.auth.base')

@section('heading')
    Reset Password
@endsection

@section('subheading')
    {{ $this->loginAction }}
@endsection

@section('content')
    {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::AUTH_PASSWORD_RESET_REQUEST_FORM_BEFORE, scopes: $this->getRenderHookScopes()) }}

    <x-filament-panels::form id="form" wire:submit="request">
        {{ $this->form }}

        <x-filament::actions
            :actions="$this->getFormActions()"
        />
    </x-filament-panels::form>

    {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::AUTH_PASSWORD_RESET_REQUEST_FORM_AFTER, scopes: $this->getRenderHookScopes()) }}
@endsection
