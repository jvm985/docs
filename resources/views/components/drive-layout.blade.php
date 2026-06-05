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
<body class="min-h-full bg-white text-slate-600">

<header class="sticky top-0 z-40 flex h-16 items-center justify-between border-b border-gray-200 bg-white px-6" data-testid="topbar">
    <a href="{{ route('projects.index') }}" class="text-lg font-bold tracking-tight text-gray-800 hover:text-gray-900">
        Docs
    </a>
    <div class="flex items-center" data-testid="user-bar" x-data="{ open: false }" @click.away="open = false">
        <button type="button" @click="open = !open" class="flex items-center gap-1 text-sm text-gray-700 hover:text-gray-900" data-testid="user-name">
            <span>{{ auth()->user()?->name }}</span>
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m6 9 6 6 6-6"/></svg>
        </button>
        <div x-show="open" x-cloak class="absolute right-6 top-12 z-30 min-w-48 overflow-hidden rounded-md border border-gray-200 bg-white py-1 text-sm shadow-md">
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button class="block w-full px-4 py-2 text-left text-gray-700 hover:bg-gray-50" data-testid="logout-button">Uitloggen</button>
            </form>
        </div>
    </div>
</header>

<div class="flex min-h-[calc(100vh-4rem)]">
    <x-drive-sidebar :scope="$scope" :activeDriveId="$activeDriveId" />

    <main class="flex-1 bg-[#f3f5f7]">

        <div class="px-4 py-6">
            <header class="mb-4 flex items-center justify-between gap-4">
                <h1 class="text-xl font-semibold text-gray-700" data-testid="page-heading">{{ $heading }}</h1>
                <div class="flex items-center gap-3">
                    {{ $actions ?? '' }}
                </div>
            </header>

            <form method="GET" action="{{ route('projects.index') }}" class="mb-4">
                <div class="relative">
                    <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
                    <input type="search" name="q" value="{{ request('q') }}" placeholder="Zoek in alle drives..."
                           data-testid="search-input"
                           class="w-full rounded border border-gray-300 bg-white py-2 pl-9 pr-3 text-sm focus:border-amber-500 focus:ring-1 focus:ring-amber-500">
                </div>
            </form>

            {{ $slot }}
        </div>
    </main>
</div>

@stack('scripts')
</body>
</html>
