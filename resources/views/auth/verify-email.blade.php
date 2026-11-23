<x-auth-card title="Verify your email" subtitle="Thanks for signing up! Please click the link we just emailed you to verify your address.">
    @if (session('status') === 'verification-link-sent')
        <flux:callout variant="success" icon="check-circle" heading="A new verification link has been sent to your email address." />
    @endif

    <div class="flex items-center justify-between gap-4">
        <form method="POST" action="{{ route('verification.send') }}">
            @csrf
            <flux:button type="submit" variant="primary">Resend verification email</flux:button>
        </form>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <flux:button type="submit" variant="ghost">Log out</flux:button>
        </form>
    </div>
</x-auth-card>
