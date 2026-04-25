<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title inertia>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <style>
            body { font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, "Noto Sans", sans-serif; }
        </style>

        <!-- Scripts -->
        @routes
        @vite(['resources/js/app.js', "resources/js/Pages/{$page['component']}.vue"])
        <script>
            // Hard refresh if we detect an old version in the DOM
            window.addEventListener('DOMContentLoaded', () => {
                const version = document.querySelector('meta[name="app-version"]')?.content;
                const currentManifest = "{{ is_file(public_path('build/manifest.json')) ? md5_file(public_path('build/manifest.json')) : 'none' }}";
                if (version && version !== currentManifest) {
                    console.log('Old version detected, forcing reload...');
                    window.location.reload(true);
                }
            });
        </script>
        <meta name="app-version" content="{{ is_file(public_path('build/manifest.json')) ? md5_file(public_path('build/manifest.json')) : time() }}">
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
