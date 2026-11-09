{{-- Profile: plain forms posting to Fortify's endpoints. --}}
@php
    $user = auth()->user();
    $status = session('status');
    $twoFactorEnabled = ! is_null($user->two_factor_secret);
    $twoFactorConfirmed = ! is_null($user->two_factor_confirmed_at);
@endphp

<x-layouts::app title="Profile">
    <x-page-header title="Profile" description="Your account details, password and two-factor authentication." />

    <div class="max-w-2xl space-y-6">
        {{-- Profile information --}}
        <form method="POST" action="{{ route('user-profile-information.update') }}">
            @csrf
            @method('PUT')
            <x-card title="Account">
                <div class="space-y-4">
                    <x-input label="Name" name="name" :value="old('name', $user->name)" required />
                    @error('name', 'updateProfileInformation') <p class="text-xs text-danger">{{ $message }}</p> @enderror
                    <x-input label="Email" name="email" type="email" :value="old('email', $user->email)" required />
                    @error('email', 'updateProfileInformation') <p class="text-xs text-danger">{{ $message }}</p> @enderror
                </div>
                <x-slot:footer>
                    @if ($status === 'profile-information-updated')
                        <span class="mr-auto text-sm text-success">Saved.</span>
                    @endif
                    <x-button type="submit">Save</x-button>
                </x-slot:footer>
            </x-card>
        </form>

        {{-- Password --}}
        <form method="POST" action="{{ route('user-password.update') }}">
            @csrf
            @method('PUT')
            <x-card title="Password">
                <div class="space-y-4">
                    <x-input label="Current password" name="current_password" type="password" required autocomplete="current-password" />
                    @error('current_password', 'updatePassword') <p class="text-xs text-danger">{{ $message }}</p> @enderror
                    <x-input label="New password" name="password" type="password" required autocomplete="new-password" />
                    @error('password', 'updatePassword') <p class="text-xs text-danger">{{ $message }}</p> @enderror
                    <x-input label="Confirm new password" name="password_confirmation" type="password" required autocomplete="new-password" />
                </div>
                <x-slot:footer>
                    @if ($status === 'password-updated')
                        <span class="mr-auto text-sm text-success">Password changed.</span>
                    @endif
                    <x-button type="submit">Change password</x-button>
                </x-slot:footer>
            </x-card>
        </form>

        {{-- Two-factor authentication --}}
        <x-card title="Two-factor authentication" description="Require a code from an authenticator app (Google Authenticator, Microsoft Authenticator, Authy) when signing in.">
            @if (! $twoFactorEnabled)
                <p class="text-sm text-muted">Two-factor authentication is <strong>off</strong>.</p>
                <form method="POST" action="{{ route('two-factor.enable') }}" class="mt-4">
                    @csrf
                    <x-button type="submit">Turn on</x-button>
                </form>
            @elseif (! $twoFactorConfirmed)
                <p class="text-sm text-muted">Scan this QR code with your authenticator app, then enter the 6-digit code to finish.</p>
                <div class="mt-4 inline-block rounded-md bg-surface p-3">{!! $user->twoFactorQrCodeSvg() !!}</div>
                <form method="POST" action="{{ route('two-factor.confirm') }}" class="mt-4 flex items-end gap-3">
                    @csrf
                    <x-input label="Code" name="code" inputmode="numeric" autocomplete="one-time-code" required class="w-40" />
                    <x-button type="submit">Confirm</x-button>
                </form>
                @error('code', 'confirmTwoFactorAuthentication') <p class="mt-1 text-xs text-danger">{{ $message }}</p> @enderror
            @else
                <p class="text-sm text-muted">Two-factor authentication is <strong class="text-success">on</strong>.</p>

                @if (in_array($status, ['two-factor-authentication-confirmed', 'recovery-codes-generated'], true))
                    <p class="mt-4 text-sm text-muted">Store these recovery codes somewhere safe. Each one can be used once if you lose your phone.</p>
                    <div class="mt-2 grid grid-cols-2 gap-1 rounded-md bg-hover p-3 font-mono text-sm">
                        @foreach ($user->recoveryCodes() as $code)
                            <span>{{ $code }}</span>
                        @endforeach
                    </div>
                @endif

                <div class="mt-4 flex gap-2">
                    <form method="POST" action="{{ route('two-factor.regenerate-recovery-codes') }}">
                        @csrf
                        <x-button type="submit" variant="secondary">New recovery codes</x-button>
                    </form>
                    <form method="POST" action="{{ route('two-factor.disable') }}">
                        @csrf
                        @method('DELETE')
                        <x-button type="submit" variant="danger">Turn off</x-button>
                    </form>
                </div>
            @endif
        </x-card>
    </div>
</x-layouts::app>
