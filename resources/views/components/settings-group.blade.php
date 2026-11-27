@props([
    'heading',
    'href',
    'empty' => false,
])

{{--
    A group of profile items on a dashboard. It always links to the settings page where the items are
    edited; with no items it only says so.
--}}
<x-site.section :heading="$heading" :href="$href" action="Settings" {{ $attributes }}>
    @if ($empty)
        <x-site.empty title="No items yet." />
    @else
        {{ $slot }}
    @endif
</x-site.section>
