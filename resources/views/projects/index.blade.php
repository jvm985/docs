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

    <main class="mx-auto max-w-4xl px-4 py-8">
        @if(session('success'))
            <div class="mb-4 rounded bg-green-50 p-3 text-sm text-green-700">{{ session('success') }}</div>
        @endif
        @if($errors->any())
            <div class="mb-4 rounded bg-red-50 p-3 text-sm text-red-600">{{ $errors->first() }}</div>
        @endif

        {{-- Mijn projecten --}}
        <div class="mb-6 flex items-center justify-between">
            <h1 class="text-2xl font-bold">Mijn projecten</h1>
            <form method="POST" action="{{ route('projects.store') }}" class="flex gap-2">
                @csrf
                <input name="name" required placeholder="Projectnaam" class="rounded border border-gray-300 px-3 py-1.5 text-sm focus:border-amber-500 focus:outline-none">
                <button type="submit" class="rounded bg-amber-500 px-4 py-1.5 text-sm font-medium text-white hover:bg-amber-600">Nieuw project</button>
            </form>
        </div>

        @if($projects->isEmpty())
            <p class="py-12 text-center text-gray-400">Geen projecten. Maak je eerste project aan.</p>
        @else
            <div class="mb-10 overflow-hidden rounded-lg border bg-white">
                <table class="w-full text-sm">
                    <thead class="border-b bg-gray-50 text-left text-xs uppercase text-gray-500">
                        <tr>
                            <th class="px-4 py-3">Naam</th>
                            <th class="px-4 py-3">Gedeeld</th>
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
                                    @if($project->shares->where('is_public', true)->count())
                                        <span class="rounded-full bg-green-100 px-2 py-0.5 text-xs text-green-700">Publiek</span>
                                    @elseif($project->shares->count())
                                        <span class="rounded-full bg-blue-100 px-2 py-0.5 text-xs text-blue-700">{{ $project->shares->count() }} gebruiker(s)</span>
                                    @else
                                        <span class="text-xs text-gray-400">Privé</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-gray-500">
                                    {{ $project->nodes_max_updated_at ? \Carbon\Carbon::parse($project->nodes_max_updated_at)->format('d/m/Y H:i') : '—' }}
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <button onclick="document.getElementById('share-{{ $project->id }}').showModal()" class="text-xs text-blue-500 hover:text-blue-700">Delen</button>
                                        <form method="POST" action="{{ route('projects.duplicate', $project) }}" class="inline">
                                            @csrf
                                            <button type="submit" class="text-xs text-gray-500 hover:text-gray-700">Kopiëren</button>
                                        </form>
                                        <form method="POST" action="{{ route('projects.destroy', $project) }}" onsubmit="return confirm('Project verwijderen?')" class="inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-xs text-red-400 hover:text-red-600">Verwijderen</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        {{-- Gedeeld met mij --}}
        @if($sharedProjects->isNotEmpty())
            <h2 class="mb-4 text-xl font-bold">Gedeeld met mij</h2>
            <div class="overflow-hidden rounded-lg border bg-white">
                <table class="w-full text-sm">
                    <thead class="border-b bg-gray-50 text-left text-xs uppercase text-gray-500">
                        <tr>
                            <th class="px-4 py-3">Naam</th>
                            <th class="px-4 py-3">Eigenaar</th>
                            <th class="px-4 py-3">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @foreach($sharedProjects as $project)
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3">
                                    <a href="{{ route('editor', $project) }}" class="font-medium text-amber-600 hover:underline">{{ $project->name }}</a>
                                </td>
                                <td class="px-4 py-3 text-gray-500">{{ $project->user->name }}</td>
                                <td class="px-4 py-3">
                                    <span class="text-xs text-gray-500">Alleen lezen</span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </main>

    {{-- Deel-dialogen --}}
    @foreach($projects as $project)
        <dialog id="share-{{ $project->id }}" class="w-full max-w-md rounded-xl p-0 shadow-2xl backdrop:bg-black/50">
            <form method="POST" action="{{ route('projects.share', $project) }}" class="p-6">
                @csrf
                <input type="hidden" name="permission" value="read">
                <h3 class="mb-4 text-lg font-bold">{{ $project->name }} delen</h3>
                <p class="mb-4 text-xs text-gray-500">Gedeelde projecten zijn altijd alleen-lezen. Anderen kunnen bestanden kopiëren naar hun eigen projecten.</p>

                <label class="mb-3 flex items-center gap-2">
                    <input type="checkbox" name="is_public" value="1" {{ $project->shares->where('is_public', true)->count() ? 'checked' : '' }}
                           onchange="this.form.querySelector('.private-opts').style.display = this.checked ? 'none' : '';">
                    <span class="text-sm">Deel met iedereen</span>
                </label>

                <div class="private-opts mb-4" style="{{ $project->shares->where('is_public', true)->count() ? 'display:none' : '' }}">
                    <label class="text-xs text-gray-500">E-mailadressen (één per regel)</label>
                    <textarea name="emails" rows="3" class="mt-1 w-full rounded border border-gray-300 px-3 py-1.5 text-sm" placeholder="naam@voorbeeld.be">{{ $project->shares->whereNotNull('user_id')->map(fn($s) => $s->user?->email)->filter()->implode("\n") }}</textarea>
                </div>

                <div class="flex justify-end gap-2">
                    <button type="button" onclick="this.closest('dialog').close()" class="px-4 py-1.5 text-sm text-gray-500 hover:text-gray-700">Annuleren</button>
                    <button type="submit" class="rounded bg-amber-500 px-4 py-1.5 text-sm font-medium text-white hover:bg-amber-600">Opslaan</button>
                </div>
            </form>
        </dialog>
    @endforeach
</body>
</html>
