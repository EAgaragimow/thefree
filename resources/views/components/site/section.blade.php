@props([
    'heading' => null,
    'count' => null,
    'href' => null,
    'action' => 'View all',
])

{{--
    A white block on the grey page with a serif heading, an optional count and an optional link to the
    full list or the settings page, as on the dashboards.
--}}
<section {{ $attributes->class('bg-white p-6 sm:p-10') }}>
    @if ($heading || $href)
        <div class="mb-6 flex items-center justify-between gap-4">
            <div class="flex min-w-0 items-center gap-3">
                @if ($heading)
                    <h2 class="font-display text-3xl">{{ $heading }}</h2>
                @endif

                @if ($count !== null)
                    <span class="bg-ink px-2 py-1 text-xs font-semibold text-white">{{ $count }}</span>
                @endif
            </div>

            @if ($href)
                <flux:button size="sm" icon-trailing="arrow-right" href="{{ $href }}">{{ $action }}</flux:button>
            @endif
        </div>
    @endif

    {{ $slot }}
</section>
