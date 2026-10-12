<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>Lumina Digital Wallet</title>

        {{-- Apply the stored theme before first paint so dark mode does not flash.
             Mirrors resources/js/composables/useTheme.ts (storage key `lumina.theme`).
             The nonce is set by the SecurityHeaders middleware when CSP is enabled. --}}
        <script nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}">
            (function () {
                try {
                    var stored = localStorage.getItem('lumina.theme');
                    var prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
                    var theme = stored === 'light' || stored === 'dark' ? stored : (prefersDark ? 'dark' : 'light');
                    var root = document.documentElement;
                    root.classList.toggle('dark', theme === 'dark');
                    root.style.colorScheme = theme;
                } catch (error) {
                    /* storage unavailable; default light theme applies */
                }
            })();
        </script>

        @vite(['resources/css/app.css', 'resources/js/app.ts'])
    </head>
    <body class="antialiased">
        <div id="app"></div>
    </body>
</html>
