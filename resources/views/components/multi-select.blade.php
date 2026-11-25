@props([
    'label',
    'options',
    'max' => null,
    'min' => null,
    'required' => false,
    'description' => null,
    'ordered' => false,
    'positionLabels' => [],
])

{{--
    A searchable multiple select that opens as a dropdown. Bind it with wire:model to an array of
    ids (as strings). `options` is a list of ['id' => string, 'name' => string] and may carry a
    'group' (shown as a heading above its options, for example a role under its function). With
    `ordered`, the order the options were picked in is kept and shown using `positionLabels`
    (for example Main, Secondary).
--}}
@php($model = $attributes->wire('model')->value())

<flux:field {{ $attributes->whereStartsWith('wire:key') }}>
    <flux:label>{{ $label }}@if ($required) *@endif</flux:label>

    @if ($description)
        <flux:description>{{ $description }}</flux:description>
    @endif

    <div
        {{ $attributes->whereStartsWith('wire:model') }}
        x-modelable="selected"
        x-data="{
            selected: [],
            open: false,
            search: '',
            options: @js($options),
            max: @js($max),
            positions: @js($positionLabels),
            nameOf(id) {
                return this.options.find((option) => option.id === String(id))?.name ?? id
            },
            has(id) {
                return this.selected.map(String).includes(id)
            },
            get filtered() {
                const query = this.search.trim().toLowerCase()

                return this.options.filter((option) => option.name.toLowerCase().includes(query))
            },
            get groups() {
                return this.filtered.reduce((groups, option) => {
                    const title = option.group ?? ''
                    let group = groups.find((candidate) => candidate.title === title)

                    if (! group) {
                        group = { title, options: [] }
                        groups.push(group)
                    }

                    group.options.push(option)

                    return groups
                }, [])
            },
            get full() {
                return this.max !== null && this.selected.length >= this.max
            },
            toggle(id) {
                if (this.has(id)) {
                    this.selected = this.selected.map(String).filter((value) => value !== id)
                } else if (! this.full) {
                    this.selected = [...this.selected.map(String), id]
                }
            },
            close() {
                this.open = false
                this.search = ''
            },
        }"
        x-on:click.outside="close()"
        x-on:keydown.escape="close()"
        class="relative"
    >
        <div
            class="flex min-h-10 w-full cursor-text flex-wrap items-center gap-1.5 rounded-lg border border-zinc-200 border-b-zinc-300/80 bg-white py-1.5 pl-2 pr-9 shadow-xs dark:border-white/10 dark:bg-white/10"
            x-bind:class="open && 'ring-2 ring-zinc-400/60 dark:ring-white/30'"
            x-on:click="open = true; $refs.search.focus()"
        >
            <template x-for="(id, index) in selected" :key="id">
                <span class="inline-flex items-center gap-1 rounded-md bg-zinc-100 py-0.5 pl-2 pr-1 text-sm dark:bg-zinc-700">
                    @if ($ordered)
                        <span class="font-medium" x-text="positions[index] ? positions[index] + ':' : ''"></span>
                    @endif
                    <span x-text="nameOf(id)"></span>
                    <button type="button" class="rounded p-0.5 hover:bg-zinc-200 dark:hover:bg-zinc-600" x-on:click.stop="toggle(String(id))" aria-label="Remove">
                        <flux:icon.x-mark variant="micro" />
                    </button>
                </span>
            </template>

            <input
                type="text"
                x-ref="search"
                x-model="search"
                x-on:focus="open = true"
                x-on:keydown.enter.prevent="filtered.length > 0 && toggle(filtered[0].id)"
                x-on:keydown.backspace="search === '' && selected.length > 0 && toggle(String(selected[selected.length - 1]))"
                x-bind:placeholder="selected.length === 0 ? 'Select…' : (full ? '' : 'Search…')"
                x-bind:disabled="full"
                autocomplete="off"
                class="min-w-24 flex-1 border-0 bg-transparent p-0 text-sm text-zinc-700 placeholder-zinc-400 outline-none focus:ring-0 dark:text-zinc-300 dark:placeholder-zinc-400"
            />

            <button
                type="button"
                class="absolute inset-y-0 right-0 flex w-9 items-center justify-center text-zinc-400"
                x-on:click.stop="open ? close() : (open = true, $refs.search.focus())"
                aria-label="Toggle options"
            >
                <flux:icon.chevron-down variant="mini" class="size-4" />
            </button>
        </div>

        <div
            x-show="open"
            x-cloak
            x-transition.opacity.duration.100ms
            class="absolute inset-x-0 z-50 mt-1 max-h-[300px] overflow-y-auto rounded-lg border border-zinc-200 bg-white py-1 shadow-lg dark:border-zinc-600 dark:bg-zinc-800"
        >
            <template x-for="group in groups" :key="group.title">
                <div>
                    <p
                        class="sticky top-0 z-10 bg-white px-3 py-1 text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400"
                        x-show="group.title !== ''"
                        x-text="group.title"
                    ></p>

                    <template x-for="option in group.options" :key="option.id">
                        <button
                            type="button"
                            class="flex w-full items-center gap-2 px-3 py-1.5 text-start text-sm hover:bg-zinc-100 disabled:opacity-50 dark:hover:bg-zinc-700"
                            x-bind:disabled="! has(option.id) && full"
                            x-on:click="toggle(option.id)"
                            x-bind:aria-pressed="has(option.id)"
                        >
                            <span class="flex size-4 shrink-0 items-center justify-center rounded border border-zinc-300 dark:border-zinc-500" x-bind:class="has(option.id) && 'border-zinc-800 bg-zinc-800 text-white dark:border-white dark:bg-white dark:text-zinc-800'">
                                <flux:icon.check variant="micro" x-show="has(option.id)" class="size-3" />
                            </span>
                            <span x-text="option.name"></span>
                        </button>
                    </template>
                </div>
            </template>

            <p class="px-3 py-2 text-sm text-zinc-500" x-show="filtered.length === 0">No matches.</p>
        </div>

        <flux:text size="sm" class="mt-1.5">
            <span x-text="selected.length"></span>
            @if ($max !== null)
                of {{ $max }}
            @endif
            selected
            @if ($min !== null)
                (at least {{ $min }})
            @endif
        </flux:text>
    </div>

    <flux:error :name="$model" />
</flux:field>
