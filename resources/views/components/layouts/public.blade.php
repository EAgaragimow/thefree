@props([
    'title' => null,
    'panel' => true,
    'bare' => false,
])

{{-- Pages that guests can open: the home page, sign in and sign up, the freelancer application. --}}
<x-layouts.site :title="$title" :panel="$panel" :bare="$bare">
    {{ $slot }}
</x-layouts.site>
