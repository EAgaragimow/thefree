<?php

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Account settings')] class extends Component {
    public bool $notify_messages = true;

    public bool $notify_projects = true;

    public bool $notify_bookings = true;

    public bool $notify_billing = true;

    public bool $savedPreferences = false;

    public string $deactivate_password = '';

    public string $deletion_password = '';

    public function mount(): void
    {
        $preferences = auth()->user()->notification_preferences ?? [];

        $this->notify_messages = $preferences['messages'] ?? true;
        $this->notify_projects = $preferences['projects'] ?? true;
        $this->notify_bookings = $preferences['bookings'] ?? true;
        $this->notify_billing = $preferences['billing'] ?? true;
    }

    public function savePreferences(): void
    {
        auth()->user()->forceFill([
            'notification_preferences' => [
                'messages' => $this->notify_messages,
                'projects' => $this->notify_projects,
                'bookings' => $this->notify_bookings,
                'billing' => $this->notify_billing,
            ],
        ])->save();

        $this->savedPreferences = true;
    }

    public function requestDataDeletion(): void
    {
        $this->validate(['deletion_password' => ['required', 'current_password:web']]);

        $user = auth()->user();

        if ($user->deletion_requested_at === null) {
            $user->forceFill(['deletion_requested_at' => now()])->save();
        }

        $this->reset('deletion_password');
    }

    public function cancelDataDeletion(): void
    {
        auth()->user()->forceFill(['deletion_requested_at' => null])->save();
    }

    public function deactivate(): void
    {
        $this->validate(['deactivate_password' => ['required', 'current_password:web']]);

        auth()->user()->forceFill(['deactivated_at' => now()])->save();

        Auth::guard('web')->logout();

        session()->invalidate();
        session()->regenerateToken();

        $this->redirectRoute('login', navigate: false);
    }
};
?>

<x-settings-layout heading="Account" subheading="Notification preferences, deactivation and personal data.">
    <div class="space-y-12">
        <form wire:submit="savePreferences" class="space-y-4">
            <flux:heading>Notification preferences</flux:heading>
            <flux:text>Choose which emails you would like to receive. In-app notifications are always on.</flux:text>

            @if ($savedPreferences)
                <flux:callout variant="success" icon="check-circle" heading="Preferences saved." />
            @endif

            <flux:switch wire:model="notify_messages" label="New messages" />
            <flux:switch wire:model="notify_projects" label="Projects and proposals" />
            <flux:switch wire:model="notify_bookings" label="Bookings and calls" />
            <flux:switch wire:model="notify_billing" label="Billing and payments" />

            <flux:button type="submit" variant="primary">Save preferences</flux:button>
        </form>

        <flux:separator />

        <section class="space-y-4">
            <flux:heading>Request personal-data deletion</flux:heading>

            @if (auth()->user()->deletion_requested_at)
                <flux:callout variant="warning" icon="exclamation-triangle" heading="Deletion requested on {{ auth()->user()->deletion_requested_at->setTimezone(auth()->user()->timezone)->format('M j, Y') }}.">
                    <x-slot name="actions">
                        <flux:button wire:click="cancelDataDeletion" size="sm">Cancel request</flux:button>
                    </x-slot>
                    An administrator will review your request.
                </flux:callout>
            @else
                <flux:text>An administrator will review your request and remove your personal data where legally possible.</flux:text>

                <form wire:submit="requestDataDeletion" class="space-y-4">
                    <flux:field>
                        <flux:label>Confirm your password</flux:label>
                        <flux:input type="password" wire:model="deletion_password" required autocomplete="current-password" />
                        <flux:error name="deletion_password" />
                    </flux:field>

                    <flux:button type="submit" variant="danger">Request deletion</flux:button>
                </form>
            @endif
        </section>

        <flux:separator />

        <section class="space-y-4">
            <flux:heading>Deactivate account</flux:heading>
            <flux:text>Your account will be hidden and you will be signed out. Signing in again reactivates it.</flux:text>

            <form wire:submit="deactivate" class="space-y-4">
                <flux:field>
                    <flux:label>Confirm your password</flux:label>
                    <flux:input type="password" wire:model="deactivate_password" required autocomplete="current-password" />
                    <flux:error name="deactivate_password" />
                </flux:field>

                <flux:button type="submit" variant="danger">Deactivate account</flux:button>
            </form>
        </section>
    </div>
</x-settings-layout>
