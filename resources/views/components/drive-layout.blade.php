@props(['scope' => 'my-drive', 'heading' => 'Mijn Drive', 'activeDriveId' => null])

<!DOCTYPE html>
<html lang="nl" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $heading }} — Docs</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>[x-cloak]{display:none!important}</style>
</head>
<body class="min-h-full bg-gray-50 text-gray-900">

<div class="flex min-h-screen">
    <x-drive-sidebar :scope="$scope" :activeDriveId="$activeDriveId" />

    <main class="flex-1">
        <div class="sticky top-0 z-30 flex items-center gap-4 border-b border-gray-200 bg-white/90 px-6 py-3 backdrop-blur">
            <form method="GET" action="{{ route('projects.index') }}" class="flex-1 max-w-xl">
                <div class="relative">
                    <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
                    <input type="search" name="q" value="{{ request('q') }}" placeholder="Zoek in alle drives..."
                           data-testid="search-input"
                           class="w-full rounded-lg border border-gray-200 bg-white py-2 pl-10 pr-4 text-sm shadow-sm focus:border-amber-400 focus:ring-2 focus:ring-amber-200">
                </div>
            </form>

            <div class="ml-auto flex items-center gap-3" data-testid="user-bar">
                <span class="hidden text-sm text-gray-700 sm:inline" data-testid="user-name">{{ auth()->user()?->name }}</span>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button class="rounded-lg border border-gray-300 px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-100" data-testid="logout-button">Uitloggen</button>
                </form>
            </div>
        </div>

        <div class="mx-auto max-w-6xl px-6 py-8">
            <header class="mb-6 flex items-center justify-between gap-4">
                <h1 class="text-2xl font-semibold" data-testid="page-heading">{{ $heading }}</h1>
                <div class="flex items-center gap-3">
                    {{ $actions ?? '' }}
                </div>
            </header>

            {{ $slot }}
        </div>
    </main>
</div>

@stack('scripts')
</body>
</html>
