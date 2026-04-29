<!DOCTYPE html>
<html lang="nl" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Projecten — Docs</title>
    @vite('resources/css/app.css')
</head>
<body class="h-full bg-gray-50 text-gray-900">
    <header class="flex h-12 items-center justify-between border-b bg-white px-6">
        <span class="text-lg font-bold">Docs</span>
        <div class="flex items-center gap-4">
            <span class="text-sm text-gray-500">{{ auth()->user()->name }}</span>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="text-sm text-gray-400 hover:text-gray-700">Uitloggen</button>
            </form>
        </div>
    </header>

    <main class="mx-auto max-w-3xl px-4 py-8">
        <div class="mb-6 flex items-center justify-between">
            <h1 class="text-2xl font-bold">Projecten</h1>
            <form method="POST" action="{{ route('projects.store') }}" class="flex gap-2">
                @csrf
                <input name="name" required placeholder="Projectnaam" class="rounded border border-gray-300 px-3 py-1.5 text-sm focus:border-amber-500 focus:outline-none">
                <button type="submit" class="rounded bg-amber-500 px-4 py-1.5 text-sm font-medium text-white hover:bg-amber-600">Nieuw project</button>
            </form>
        </div>

        @if($errors->any())
            <div class="mb-4 rounded bg-red-50 p-3 text-sm text-red-600">{{ $errors->first() }}</div>
        @endif

        @if($projects->isEmpty())
            <p class="py-12 text-center text-gray-400">Geen projecten. Maak je eerste project aan.</p>
        @else
            <div class="overflow-hidden rounded-lg border bg-white">
                <table class="w-full text-sm">
                    <thead class="border-b bg-gray-50 text-left text-xs uppercase text-gray-500">
                        <tr>
                            <th class="px-4 py-3">Naam</th>
                            <th class="px-4 py-3">Laatste wijziging</th>
                            <th class="px-4 py-3 text-right">Acties</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @foreach($projects as $project)
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3">
                                    <a href="{{ route('editor', $project) }}" class="font-medium text-amber-600 hover:underline">{{ $project->name }}</a>
                                </td>
                                <td class="px-4 py-3 text-gray-500">
                                    {{ $project->nodes_max_updated_at ? \Carbon\Carbon::parse($project->nodes_max_updated_at)->format('d/m/Y H:i') : '—' }}
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <form method="POST" action="{{ route('projects.destroy', $project) }}" onsubmit="return confirm('Project verwijderen?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-xs text-red-400 hover:text-red-600">Verwijderen</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </main>
</body>
</html>
