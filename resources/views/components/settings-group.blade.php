@props([
    'heading',
    'href',
    'empty' => false,
])

{{--
    A group of profile items on a dashboard. It always links to the settings page where the items are
    edited; with no items it only says so.
--}}
<flux:card {{ $attributes->class('space-y-3') }}>
    <div class="flex items-center justify-between gap-3">
        <flux:heading>{{ $heading }}</flux:heading>
        <flux:button size="sm" variant="ghost" icon-trailing="arrow-right" href="{{ $href }}">Settings</flux:button>
    </div>

    @if ($empty)
        <flux:text>No items yet.</flux:text>
    @else
        {{ $slot }}
    @endif
</flux:card>
