<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>{{ isset($title) && $title ? $title.' · ' : '' }}{{ app(\App\Modules\Core\Services\Settings::class)->get('company.name') }}</title>
<script>
    // Set the theme before the page paints, so dark mode never flashes white.
    (() => {
        let saved = null;
        try { saved = localStorage.getItem('theme'); } catch (e) {}
        const dark = saved === 'dark' || (saved !== 'light' && window.matchMedia('(prefers-color-scheme: dark)').matches);
        document.documentElement.classList.toggle('dark', dark);
    })();
</script>
@vite(['resources/css/app.css', 'resources/js/app.js'])
