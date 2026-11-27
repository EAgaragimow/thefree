@props(['title', 'subtitle' => null])

<x-layouts.public :title="$title" :panel="false">
    <div class="mx-auto w-full max-w-md bg-white p-8 sm:p-10">
        <flux:heading size="xl" level="1">{{ $title }}</flux:heading>

        @if ($subtitle)
            <flux:text class="mt-2">{{ $subtitle }}</flux:text>
        @endif

        @if (session('status'))
            <flux:callout variant="success" icon="check-circle" class="mt-6" heading="{{ session('status') }}" />
        @endif

        <div class="mt-6 space-y-6">
            {{ $slot }}
        </div>
    </div>
</x-layouts.public>
