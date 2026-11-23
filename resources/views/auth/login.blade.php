<x-auth-card title="Log in" subtitle="Welcome back. Sign in to your account.">
    <form method="POST" action="{{ route('login') }}" class="space-y-6">
        @csrf

        <flux:field>
            <flux:label>Email</flux:label>
            <flux:input type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="email" />
            <flux:error name="email" />
        </flux:field>

        <flux:field>
            <flux:label>Password</flux:label>
            <flux:input type="password" name="password" required autocomplete="current-password" viewable />
            <flux:error name="password" />
        </flux:field>

        <flux:checkbox name="remember" label="Remember me" />

        <flux:button type="submit" variant="primary" class="w-full">Log in</flux:button>
    </form>

    <div class="flex justify-between text-sm">
        <flux:link href="{{ route('password.request') }}">Forgot your password?</flux:link>
        <flux:link href="{{ route('register') }}">Create an account</flux:link>
    </div>
</x-auth-card>
