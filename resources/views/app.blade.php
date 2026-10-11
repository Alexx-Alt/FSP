<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @class(['dark' => ($appearance ?? 'system') == 'dark'])>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        {{-- Resolve the theme before CSS/Vue mount, matching the original Nova approach. --}}
        <script>
            (function() {
                try {
                    const serverAppearance = '{{ $appearance ?? "system" }}';
                    const storedAppearance = localStorage.getItem('appearance');
                    const appearance = storedAppearance || serverAppearance || 'system';
                    const dark = appearance === 'dark'
                        || (appearance === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);

                    document.documentElement.classList.toggle('dark', dark);
                    document.documentElement.dataset.theme = dark ? 'midnight' : 'light';
                    document.documentElement.style.colorScheme = dark ? 'dark' : 'light';
                } catch (e) {
                    document.documentElement.classList.remove('dark');
                    document.documentElement.dataset.theme = 'light';
                }
            })();
        </script>

        <style>
            html,
            body {
                margin: 0;
                background: #f6f4f9;
                color: #1f1a29;
            }

            html.dark,
            html.dark body,
            html[data-theme="midnight"],
            html[data-theme="midnight"] body {
                background: #0d0b11;
                color: #f7f3fb;
            }
        </style>

        <link rel="icon" href="/brand/fsp-logo.svg" type="image/svg+xml">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">

        @fonts

        @vite(['resources/css/app.css', 'resources/js/app.ts', "resources/js/pages/{$page['component']}.vue"])
        <x-inertia::head>
            <title>{{ config('app.name', 'Laravel') }}</title>
        </x-inertia::head>
    </head>
    <body class="font-sans antialiased">
        <x-inertia::app />
    </body>
</html>
