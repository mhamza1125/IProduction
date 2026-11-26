<x-layouts::guest>
    <x-slot:heading>Sign in to your account</x-slot:heading>

    <form method="POST" action="{{ route('login.store') }}" class="space-y-4">
        @csrf
        <x-input label="Email or username" name="login" :value="old('login')" required autofocus autocomplete="username" />
        <x-input label="Password" name="password" type="password" required autocomplete="current-password" />

        <div class="flex items-center justify-between">
            <x-checkbox name="remember" label="Remember me" />
            <span class="text-sm text-subtle">Forgot your password? Ask an administrator.</span>
        </div>

        <x-button type="submit" class="w-full">Sign in</x-button>
    </form>

    @if (config('iproduction.demo_login.enabled'))
        <div class="mt-6 border-t border-line pt-6">
            <form method="POST" action="{{ route('demo-login') }}">
                @csrf
                <x-button type="submit" variant="secondary" class="w-full">Sign in as demo admin</x-button>
            </form>
            <p class="mt-2 text-center text-xs text-subtle">One click, no password needed. For demonstrations only.</p>
        </div>
    @endif
</x-layouts::guest>
