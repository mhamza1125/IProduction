<x-layouts::guest>
    <x-slot:heading>Confirm your password</x-slot:heading>

    <p class="mb-4 text-sm text-zinc-600 dark:text-zinc-400">This is a secure area. Please confirm your password to continue.</p>

    <form method="POST" action="{{ route('password.confirm.store') }}" class="space-y-4">
        @csrf
        <x-input label="Password" name="password" type="password" required autofocus autocomplete="current-password" />
        <x-button type="submit" class="w-full">Confirm</x-button>
    </form>
</x-layouts::guest>
