@props([
    'heading' => null,
    'count' => null,
    'href' => null,
    'action' => 'View all',
    'actions' => null,
])

{{--
    A white block on the grey page with a serif heading, an optional count and optional header actions
    (an `actions` slot, or a link via `href`), as on the dashboards. Give it an `id` to make it an
    anchor of the sticky tab bar.
--}}
<section {{ $attributes->class('scroll-mt-24 bg-white p-6 sm:p-10') }}>
    @if ($heading || $href || $actions)
        <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
            <div class="flex min-w-0 items-center gap-3">
                @if ($heading)
                    <h2 class="font-display text-3xl">{{ $heading }}</h2>
                @endif

                @if ($count !== null)
                    <span class="bg-ink px-2 py-1 text-xs font-semibold text-white">{{ $count }}</span>
                @endif
            </div>

            @if ($actions)
                <div class="flex flex-wrap gap-2">{{ $actions }}</div>
            @elseif ($href)
                <flux:button size="sm" icon-trailing="arrow-right" href="{{ $href }}">{{ $action }}</flux:button>
            @endif
        </div>
    @endif

    {{ $slot }}
</section>
