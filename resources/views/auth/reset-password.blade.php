<x-layouts::guest>
    <x-slot:heading>Choose a new password</x-slot:heading>

    <form method="POST" action="{{ route('password.update') }}" class="space-y-4">
        @csrf
        <input type="hidden" name="token" value="{{ $request->route('token') }}">
        <x-input label="Email" name="email" type="email" :value="old('email', $request->email)" required autocomplete="username" />
        <x-input label="New password" name="password" type="password" required autocomplete="new-password" />
        <x-input label="Confirm password" name="password_confirmation" type="password" required autocomplete="new-password" />
        <x-button type="submit" class="w-full">Reset password</x-button>
    </form>
</x-layouts::guest>
