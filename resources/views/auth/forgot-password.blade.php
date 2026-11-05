<x-layouts::guest>
    <x-slot:heading>Reset your password</x-slot:heading>

    <p class="mb-4 text-sm text-zinc-600 dark:text-zinc-400">Enter your email and we'll send you a link to choose a new password.</p>

    <form method="POST" action="{{ route('password.email') }}" class="space-y-4">
        @csrf
        <x-input label="Email" name="email" type="email" :value="old('email')" required autofocus />
        <x-button type="submit" class="w-full">Email reset link</x-button>
    </form>

    <p class="mt-4 text-center text-sm"><a href="{{ route('login') }}" class="text-brand-600 hover:underline dark:text-brand-500">Back to sign in</a></p>
</x-layouts::guest>
