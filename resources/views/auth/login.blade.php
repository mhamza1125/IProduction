<x-layouts::guest>
    <x-slot:heading>Sign in to your account</x-slot:heading>

    <form method="POST" action="{{ route('login.store') }}" class="space-y-4">
        @csrf
        <x-input label="Email" name="email" type="email" :value="old('email')" required autofocus autocomplete="username" />
        <x-input label="Password" name="password" type="password" required autocomplete="current-password" />

        <div class="flex items-center justify-between">
            <x-checkbox name="remember" label="Remember me" />
            <a href="{{ route('password.request') }}" class="text-sm text-brand-600 hover:underline dark:text-brand-500">Forgot password?</a>
        </div>

        <x-button type="submit" class="w-full">Sign in</x-button>
    </form>
</x-layouts::guest>
