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
        <div class="mx-auto max-w-6xl px-6 py-8">
            <header class="mb-6 flex items-center justify-between gap-4">
                <h1 class="text-2xl font-semibold" data-testid="page-heading">{{ $heading }}</h1>
                <div class="flex items-center gap-3">
                    {{ $actions ?? '' }}
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button class="rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-700 hover:bg-gray-100">Uitloggen</button>
                    </form>
                </div>
            </header>

            {{ $slot }}
        </div>
    </main>
</div>

@stack('scripts')
</body>
</html>
