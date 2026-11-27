@props(['title', 'text' => null])

{{-- The bordered "nothing here yet" box used inside a section. --}}
<div {{ $attributes->class('rounded border border-zinc-200 p-5') }}>
    <p class="font-medium">{{ $title }}</p>

    @if ($text)
        <p class="mt-1 text-sm text-zinc-500">{{ $text }}</p>
    @endif
</div>
