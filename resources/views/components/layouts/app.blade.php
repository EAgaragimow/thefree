@props(['title' => null])

{{--
    The signed-in pages of brands and freelancers. Their areas have a tab bar under the header; other
    pages (directory, blog, messages, settings) have none.
--}}
@php
    $user = auth()->user();

    $tabs = match (true) {
        $user?->isClient() && request()->routeIs('client.*') => [
            ['label' => 'Overview', 'href' => route('client.dashboard'), 'current' => request()->routeIs('client.dashboard')],
            ['label' => 'Calls', 'href' => route('client.calls'), 'current' => request()->routeIs('client.calls')],
            ['label' => 'Projects', 'href' => route('client.projects'), 'current' => request()->routeIs('client.projects')],
            ['label' => 'Favourites', 'href' => route('client.favorites'), 'current' => request()->routeIs('client.favorites')],
            ['label' => 'Recommended', 'href' => route('client.recommended'), 'current' => request()->routeIs('client.recommended')],
        ],
        $user?->isFreelancer() && request()->routeIs('freelancer.*') && ! request()->routeIs('freelancer.preview') => [
            ['label' => 'Overview', 'href' => route('freelancer.dashboard'), 'current' => request()->routeIs('freelancer.dashboard')],
            ['label' => 'My profile', 'href' => route('freelancer.profile'), 'current' => request()->routeIs('freelancer.profile')],
            ['label' => 'Highlights', 'href' => route('freelancer.highlights'), 'current' => request()->routeIs('freelancer.highlights')],
            ['label' => 'Services', 'href' => route('freelancer.services'), 'current' => request()->routeIs('freelancer.services')],
            ['label' => 'Consultations', 'href' => route('freelancer.consultations'), 'current' => request()->routeIs('freelancer.consultations')],
        ],
        default => null,
    };
@endphp

<x-layouts.site :title="$title" :tabs="$tabs" :panel="! request()->routeIs('client.dashboard', 'freelancer.dashboard')">
    {{ $slot }}
</x-layouts.site>
