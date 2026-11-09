{{-- Layout for sign-in and password pages. --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    @include('partials.head')
</head>
<body class="flex min-h-full items-center justify-center bg-page px-4 py-12 text-fg">
    <div class="w-full max-w-sm">
        <div class="mb-6 text-center">
            @if ($logo = app(\App\Modules\Core\Services\Settings::class)->get('company.logo_path'))
                <img src="{{ Storage::disk('public')->url($logo) }}" alt="" class="mx-auto mb-3 h-12 w-auto">
            @endif
            <p class="text-lg font-semibold">{{ app(\App\Modules\Core\Services\Settings::class)->get('company.name') }}</p>
            @isset($heading)
                <h1 class="mt-1 text-sm text-subtle">{{ $heading }}</h1>
            @endisset
        </div>

        <div class="rounded-lg bg-surface p-6 shadow-sm ring-1 ring-line">
            @if (session('status'))
                <p class="mb-4 rounded-md bg-green-50 px-3 py-2 text-sm text-green-700 dark:bg-green-500/10 dark:text-green-400">{{ session('status') }}</p>
            @endif

            {{ $slot }}
        </div>
    </div>
</body>
</html>
