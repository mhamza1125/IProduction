<x-layouts::guest>
    <x-slot:heading>Sign in to your account</x-slot:heading>

    <form method="POST" action="{{ route('login.store') }}" class="space-y-4">
        @csrf
        <x-input label="Email or username" name="login" :value="old('login')" required autofocus autocomplete="username" />
        <x-input label="Password" name="password" type="password" required autocomplete="current-password" />

        <div class="flex items-center justify-between">
            <x-checkbox name="remember" label="Remember me" />
            <a href="{{ route('password.request') }}" class="text-sm text-link hover:underline">Forgot password?</a>
        </div>

        <x-button type="submit" class="w-full">Sign in</x-button>
    </form>
</x-layouts::guest>
