<x-filament-panels::page>
    <x-filament-panels::form
        id="form"
        :wire:key="$this->getId() . '.forms.' . $this->getFormStatePath()"
        wire:submit="save"
    >
        {{ $this->form }}

        <x-filament::actions
            :actions="$this->getFormActions()"
        />
    </x-filament-panels::form>

</x-filament-panels::page>
