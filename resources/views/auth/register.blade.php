<x-auth-card title="Create your account" subtitle="Sign up as a Client to find vetted freelancers.">
    <form method="POST" action="{{ route('register') }}" class="space-y-6" x-data x-init="$refs.timezone.value = Intl.DateTimeFormat().resolvedOptions().timeZone || 'UTC'">
        @csrf

        <input type="hidden" name="timezone" x-ref="timezone" value="UTC">

        <flux:field>
            <flux:label>Name</flux:label>
            <flux:input name="name" value="{{ old('name') }}" required autofocus autocomplete="name" />
            <flux:error name="name" />
        </flux:field>

        <flux:field>
            <flux:label>Email</flux:label>
            <flux:input type="email" name="email" value="{{ old('email') }}" required autocomplete="email" />
            <flux:error name="email" />
        </flux:field>

        <flux:field>
            <flux:label>Password</flux:label>
            <flux:input type="password" name="password" required autocomplete="new-password" viewable />
            <flux:error name="password" />
        </flux:field>

        <flux:field>
            <flux:label>Confirm password</flux:label>
            <flux:input type="password" name="password_confirmation" required autocomplete="new-password" viewable />
        </flux:field>

        <flux:button type="submit" variant="primary" class="w-full">Create account</flux:button>
    </form>

    <flux:text class="text-sm">
        Already have an account? <flux:link href="{{ route('login') }}">Log in</flux:link>
    </flux:text>
</x-auth-card>
