@php
    $gridDirection = $getGridDirection() ?? 'column';
    $isBulkToggleable = $isBulkToggleable();
    $isDisabled = $isDisabled();
    $isSearchable = $isSearchable();
    $statePath = $getStatePath();
@endphp

<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div
        x-data="{
            areAllCheckboxesChecked: false,

            checkboxListOptions: Array.from(
                $root.querySelectorAll('.fi-fo-checkbox-list-option-label'),
            ),

            search: '',

            visibleCheckboxListOptions: [],

            checkIfAllCheckboxesAreChecked: function () {
                this.areAllCheckboxesChecked =
                    this.visibleCheckboxListOptions.length ===
                    this.visibleCheckboxListOptions.filter((checkboxLabel) =>
                        checkboxLabel.querySelector('input[type=checkbox]:checked'),
                    ).length
            },

            toggleAllCheckboxes: function () {
                state = ! this.areAllCheckboxesChecked

                this.visibleCheckboxListOptions.forEach((checkboxLabel) => {
                    checkbox = checkboxLabel.querySelector('input[type=checkbox]')

                    checkbox.checked = state
                    checkbox.dispatchEvent(new Event('change'))
                })

                this.areAllCheckboxesChecked = state
            },

            updateVisibleCheckboxListOptions: function () {
                this.visibleCheckboxListOptions = this.checkboxListOptions.filter(
                    (checkboxListItem) => {
                        if (
                            checkboxListItem
                                .querySelector('.fi-fo-checkbox-list-option-label')
                                ?.innerText.toLowerCase()
                                .includes(this.search.toLowerCase())
                        ) {
                            return true
                        }

                        return checkboxListItem
                            .querySelector('.fi-fo-checkbox-list-option-description')
                            ?.innerText.toLowerCase()
                            .includes(this.search.toLowerCase())
                    },
                )
            },
        }"
        x-init="
            updateVisibleCheckboxListOptions()

            $nextTick(() => {
                checkIfAllCheckboxesAreChecked()
            })

            Livewire.hook('commit', ({ component, commit, succeed, fail, respond }) => {
                succeed(({ snapshot, effect }) => {
                    $nextTick(() => {
                        if (component.id !== @js($this->getId())) {
                            return
                        }

                        checkboxListOptions = Array.from(
                            $root.querySelectorAll('.fi-fo-checkbox-list-option-label'),
                        )

                        updateVisibleCheckboxListOptions()

                        checkIfAllCheckboxesAreChecked()
                    })
                })
            })

            $watch('search', () => {
                updateVisibleCheckboxListOptions()
                checkIfAllCheckboxesAreChecked()
            })
        "
    >
        @if (! $isDisabled)
            @if ($isSearchable && count($getOptions()))
                <x-filament::input.wrapper
                    inline-prefix
                    prefix-icon="heroicon-m-magnifying-glass"
                    prefix-icon-alias="forms:components.checkbox-list.search-field"
                    class="mb-4"
                >
                    <x-filament::input
                        inline-prefix
                        :placeholder="$getSearchPrompt()"
                        type="search"
                        :attributes="
                            \Filament\Support\prepare_inherited_attributes(
                                new \Illuminate\View\ComponentAttributeBag([
                                    'x-model.debounce.' . $getSearchDebounce() => 'search',
                                ])
                            )
                        "
                    />
                </x-filament::input.wrapper>
            @endif

            @if ($isBulkToggleable && count($getOptions()))
                <div
                    x-cloak
                    class="mb-2"
                    wire:key="{{ $this->getId() }}.{{ $getStatePath() }}.{{ $field::class }}.actions"
                >
                    <span
                        x-show="! areAllCheckboxesChecked"
                        x-on:click="toggleAllCheckboxes()"
                        wire:key="{{ $this->getId() }}.{{ $statePath }}.{{ $field::class }}.actions.select-all"
                    >
                        {{ $getAction('selectAll') }}
                    </span>

                    <span
                        x-show="areAllCheckboxesChecked"
                        x-on:click="toggleAllCheckboxes()"
                        wire:key="{{ $this->getId() }}.{{ $statePath }}.{{ $field::class }}.actions.deselect-all"
                    >
                        {{ $getAction('deselectAll') }}
                    </span>
                </div>
            @endif
        @endif

        @if ($getTimesheets()->isEmpty())
            <span class="text-sm text-custom-600 dark:text-custom-400" style="--c-400:var(--warning-400);--c-600:var(--warning-600);">
                Nothing to verify.
            </span>
        @else
            <div
                @if($isSearchable)
                    x-show="visibleCheckboxListOptions.length"
                @endif
                @style([
                    'grid-template-columns: repeat('.($getColumns('default') ?: '1').', minmax(0, 1fr))' => $gridDirection !== 'column' && $getColumns('default'),
                ])
                @class([
                    'fi-fo-checkbox-list gap-4',
                    '-mt-4' => $gridDirection === 'column',
                    'grid' => $gridDirection !== 'column',
                    'flex flex-col' => $gridDirection === 'column',
                ])
                {{
                    \Filament\Support\prepare_inherited_attributes($attributes)
                        ->merge($getExtraAttributes(), escape: false)
                }}
            >

                @forelse ($getOptions() as $value => $label)
                    <div
                        wire:key="{{ $this->getId() }}.{{ $statePath }}.{{ $field::class }}.options.{{ $value }}"
                        @if ($isSearchable)
                            x-show="
                                $el
                                    .querySelector('.fi-fo-checkbox-list-option-label')
                                    ?.innerText.toLowerCase()
                                    .includes(search.toLowerCase()) ||
                                    $el
                                        .querySelector('.fi-fo-checkbox-list-option-description')
                                        ?.innerText.toLowerCase()
                                        .includes(search.toLowerCase())
                            "
                        @endif
                        @class([
                            'break-inside-avoid pt-4' => $gridDirection === 'column',
                        ])
                    >
                        <label class="fi-fo-checkbox-list-option-label gap-x-3" style="cursor:pointer;">
                            <div class="flex fi-fo-checkbox-list-option-label gap-x-3">
                                <x-filament::input.checkbox
                                    :valid="! $errors->has($statePath)"
                                    :attributes="
                                        \Filament\Support\prepare_inherited_attributes($getExtraInputAttributeBag())
                                            ->merge([
                                                'disabled' => $isDisabled || $isOptionDisabled($value, $label),
                                                'value' => $value,
                                                'wire:loading.attr' => 'disabled',
                                                $applyStateBindingModifiers('wire:model') => $statePath,
                                                'x-on:change' => $isBulkToggleable ? 'checkIfAllCheckboxesAreChecked()' : null,
                                            ], escape: false)
                                            ->class(['mt-1'])
                                    "
                                />

                                <div class="grid text-sm leading-6">
                                    <span
                                        class="overflow-hidden font-medium break-words fi-fo-checkbox-list-option-label text-gray-950 dark:text-white"
                                    >
                                        {{ $label }}
                                    </span>

                                    @if ($hasDescription($value))
                                        <p
                                            class="text-gray-500 fi-fo-checkbox-list-option-description dark:text-gray-400"
                                        >
                                            {{ $getDescription($value) }}
                                        </p>
                                    @endif
                                </div>
                            </div>

                            @php($timesheet = $getTimesheets()->first(fn ($timesheet) => $timesheet->id === $value))

                            <div class="month" hidden>
                                {{ $timesheet->month }}
                            </div>

                            <div>
                                @include('filament.validation.pages.preview', [
                                    'timesheets' => [$timesheet],
                                ])
                            </div>
                        </label>
                    </div>
                @empty
                    <div
                        wire:key="{{ $this->getId() }}.{{ $statePath }}.{{ $field::class }}.empty"
                    ></div>
                @endforelse
            </div>
        @endif

        @if ($isSearchable)
            <div
                x-cloak
                x-show="search && ! visibleCheckboxListOptions.length"
                class="text-sm text-gray-500 fi-fo-checkbox-list-no-search-results-message dark:text-gray-400"
            >
                {{ $getNoSearchResultsMessage() }}
            </div>
        @endif
    </div>

    <style>
        {!! File::get(base_path('resources/css/print.css')) !!}
        {!! File::get(base_path('resources/css/fonts.css')) !!}

        .undertime-badge {
            width: 12pt !important;
            height: 12pt !important;
        }
        @media (prefers-color-scheme: dark) {
            .undertime-badge {
                color: gray;
                background-color: #FFF;
            }
            td {
                border-color: #333 !important;
            }
        }
    </style>
</x-dynamic-component>
