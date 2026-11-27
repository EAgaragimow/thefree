@props(['items'])

{{--
    The sticky white bar under the header. Each item is an anchor to a section of the dashboard
    (`href` is "#section-id"); the item of the section being read is highlighted as the page scrolls,
    and on narrow screens the bar scrolls sideways by itself to keep that item in the middle.
--}}
@php
    $ids = collect($items)->map(fn (array $item): string => ltrim($item['href'], '#'))->values()->all();
@endphp

<nav
    class="sticky top-0 z-30 bg-white shadow-xs"
    aria-label="Sections"
    x-data="{
        ids: @js($ids),
        active: @js($ids[0] ?? null),
        centre(id) {
            const scroller = this.$refs.scroller
            const link = scroller.querySelector('[data-anchor=' + id + ']')

            if (! link) {
                return
            }

            scroller.scrollTo({
                left: link.offsetLeft - (scroller.clientWidth - link.offsetWidth) / 2,
                behavior: 'smooth',
            })
        },
        update() {
            const atBottom = window.innerHeight + window.scrollY >= document.documentElement.scrollHeight - 2
            let current = this.ids[0]

            for (const id of this.ids) {
                const element = document.getElementById(id)

                if (element && element.getBoundingClientRect().top <= 140) {
                    current = id
                }
            }

            this.active = atBottom ? this.ids[this.ids.length - 1] : current
        },
    }"
    x-init="update(); centre(active); $watch('active', (id) => centre(id))"
    x-on:scroll.window.throttle.50ms="update()"
    x-on:resize.window.throttle.100ms="update()"
>
    <div x-ref="scroller" class="relative mx-auto flex w-full max-w-7xl gap-1 overflow-x-auto px-4 py-4 sm:px-6">
        @foreach ($items as $item)
            <a
                href="{{ $item['href'] }}"
                data-anchor="{{ ltrim($item['href'], '#') }}"
                class="shrink-0 px-5 py-3 text-sm font-semibold uppercase tracking-wide text-ink transition"
                x-bind:class="active === '{{ ltrim($item['href'], '#') }}' ? 'bg-tab' : 'hover:bg-zinc-100'"
                x-bind:aria-current="active === '{{ ltrim($item['href'], '#') }}' ? 'location' : null"
                x-on:click="active = '{{ ltrim($item['href'], '#') }}'"
            >{{ $item['label'] }}</a>
        @endforeach
    </div>
</nav>
