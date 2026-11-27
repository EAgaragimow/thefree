@props(['items'])

{{-- The white tab bar under the header: the sections of a brand's or a freelancer's area. --}}
<nav class="bg-white" aria-label="Sections">
    <div class="mx-auto flex w-full max-w-7xl gap-1 overflow-x-auto px-4 py-5 sm:px-6">
        @foreach ($items as $item)
            <a
                href="{{ $item['href'] }}"
                @if ($item['current']) aria-current="page" @endif
                class="shrink-0 px-5 py-3 text-sm font-semibold uppercase tracking-wide text-ink transition {{ $item['current'] ? 'bg-tab' : 'hover:bg-zinc-100' }}"
            >{{ $item['label'] }}</a>
        @endforeach
    </div>
</nav>
