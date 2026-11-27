@props(['title' => null])

{{--
    The signed-in pages of brands and freelancers. Only the two dashboards have a tab bar: it is sticky
    and its items are anchors to the sections of the dashboard.
--}}
@php
    $user = auth()->user();

    $tabs = match (true) {
        $user?->isClient() && request()->routeIs('client.dashboard') => [
            ['label' => 'Overview', 'href' => '#overview'],
            ['label' => 'Calls', 'href' => '#calls'],
            ['label' => 'Projects', 'href' => '#projects'],
            ['label' => 'Messages', 'href' => '#messages'],
        ],
        $user?->isFreelancer() && request()->routeIs('freelancer.dashboard') => [
            ['label' => 'Overview', 'href' => '#overview'],
            ['label' => 'Messages', 'href' => '#messages'],
            ['label' => 'Requests', 'href' => '#requests'],
            ['label' => 'Calls', 'href' => '#calls'],
            ['label' => 'Projects', 'href' => '#projects'],
            ['label' => 'Highlights', 'href' => '#highlights'],
            ['label' => 'Services', 'href' => '#services'],
            ['label' => 'Work history', 'href' => '#work-history'],
            ['label' => 'Consultations', 'href' => '#consultations'],
        ],
        default => null,
    };
@endphp

<x-layouts.site :title="$title" :tabs="$tabs" :panel="! request()->routeIs('client.dashboard', 'freelancer.dashboard')">
    {{ $slot }}
</x-layouts.site>
