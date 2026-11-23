<x-auth-card title="Forgot your password?" subtitle="Enter your email and we will send you a link to reset it.">
    <form method="POST" action="{{ route('password.email') }}" class="space-y-6">
        @csrf

        <flux:field>
            <flux:label>Email</flux:label>
            <flux:input type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="email" />
            <flux:error name="email" />
        </flux:field>

        <flux:button type="submit" variant="primary" class="w-full">Email reset link</flux:button>
    </form>

    <flux:text class="text-sm">
        <flux:link href="{{ route('login') }}">Back to log in</flux:link>
    </flux:text>
</x-auth-card>
