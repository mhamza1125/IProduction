<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>{{ isset($title) && $title ? $title.' · ' : '' }}{{ app(\App\Modules\Core\Services\Settings::class)->get('company.name') }}</title>
<script>
    // Set the theme before the page paints, so it never flashes (same rules as resources/js/theme.js).
    (() => {
        let saved = null;
        try { saved = localStorage.getItem('theme'); } catch (e) {}
        const theme = ['light', 'dim', 'dark'].includes(saved) ? saved : 'system';
        const systemDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
        document.documentElement.classList.toggle('dark', theme === 'dark' || theme === 'dim' || (theme === 'system' && systemDark));
        document.documentElement.classList.toggle('dim', theme === 'dim');
    })();
</script>
@vite(['resources/css/app.css', 'resources/js/app.js'])
