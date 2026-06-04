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

<header class="sticky top-0 z-40 flex h-14 items-center justify-between border-b border-gray-200 bg-white px-4 text-sm" data-testid="topbar">
    <a href="{{ route('projects.index') }}" class="flex items-center gap-2 font-semibold text-gray-900">
        <span class="text-lg tracking-tight">Docs</span>
    </a>
    <div class="flex items-center gap-3" data-testid="user-bar">
        <span class="hidden text-gray-700 sm:inline" data-testid="user-name">{{ auth()->user()?->name }}</span>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button class="rounded-md border border-gray-300 px-3 py-1 text-xs font-medium text-gray-700 transition hover:bg-gray-100" data-testid="logout-button">Uitloggen</button>
        </form>
    </div>
</header>

<div class="flex min-h-[calc(100vh-3.5rem)]">
    <x-drive-sidebar :scope="$scope" :activeDriveId="$activeDriveId" />

    <main class="flex-1 bg-[#f3f5f7]">

        <div class="mx-auto max-w-6xl px-6 py-8">
            <header class="mb-6 flex items-center justify-between gap-4">
                <h1 class="text-2xl font-semibold text-gray-900" data-testid="page-heading">{{ $heading }}</h1>
                <div class="flex items-center gap-3">
                    {{ $actions ?? '' }}
                </div>
            </header>

            <form method="GET" action="{{ route('projects.index') }}" class="mb-5">
                <div class="relative">
                    <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
                    <input type="search" name="q" value="{{ request('q') }}" placeholder="Zoek in alle drives..."
                           data-testid="search-input"
                           class="w-full rounded-md border border-gray-200 bg-white py-2.5 pl-10 pr-4 text-sm shadow-sm focus:border-amber-500 focus:ring-1 focus:ring-amber-500">
                </div>
            </form>

            {{ $slot }}
        </div>
    </main>
</div>

@stack('scripts')
</body>
</html>
