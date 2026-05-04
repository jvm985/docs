<!DOCTYPE html>
<html lang="nl" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Mijn projecten — Docs</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>[x-cloak]{display:none!important}</style>
</head>
<body class="min-h-full bg-gray-50 text-gray-900">

<div x-data="projectsPage()" class="mx-auto max-w-5xl px-6 py-8">

    <header class="mb-8 flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-semibold">Projecten</h1>
            <p class="text-sm text-gray-500">Welkom, {{ auth()->user()->name }}</p>
        </div>
        <div class="flex items-center gap-3">
            <button type="button" @click="showCreate = true" class="rounded-lg bg-amber-500 px-4 py-2 text-sm font-medium text-white hover:bg-amber-600">+ Nieuw project</button>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button class="rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-700 hover:bg-gray-100">Uitloggen</button>
            </form>
        </div>
    </header>

    {{-- Create dialog --}}
    <div x-show="showCreate" x-cloak class="fixed inset-0 z-40 flex items-center justify-center bg-black/40">
        <form method="POST" action="{{ route('projects.store') }}" class="w-full max-w-sm rounded-xl bg-white p-6 shadow-xl">
            @csrf
            <h2 class="mb-3 text-lg font-semibold">Nieuw project</h2>
            <input type="text" name="name" required maxlength="120" autofocus class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm" data-testid="new-project-name">
            <div class="mt-4 flex justify-end gap-2">
                <button type="button" @click="showCreate = false" class="rounded-lg px-3 py-1.5 text-sm text-gray-500 hover:bg-gray-100">Annuleer</button>
                <button class="rounded-lg bg-amber-500 px-3 py-1.5 text-sm font-medium text-white hover:bg-amber-600">Aanmaken</button>
            </div>
        </form>
    </div>

    {{-- Eigen projecten --}}
    <section class="mb-8">
        <h2 class="mb-2 text-sm font-semibold uppercase tracking-wider text-gray-500">Mijn projecten</h2>
        @if($ownProjects->isEmpty())
            <p class="text-sm text-gray-400" data-testid="no-own-projects">Nog geen projecten. Klik op «Nieuw project» om te beginnen.</p>
        @else
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3" data-testid="own-projects">
                @foreach($ownProjects as $project)
                    <div class="group relative flex flex-col rounded-xl border border-gray-200 bg-white p-4 shadow-sm" data-testid="project-card">
                        <div class="flex items-start justify-between gap-2">
                            <a href="{{ route('editor', $project) }}" class="text-base font-medium text-gray-900 hover:text-amber-600" data-testid="project-link">{{ $project->name }}</a>
                            <div class="relative" @click.away="menuOpen = (menuOpen === {{ $project->id }} ? null : menuOpen)">
                                <button type="button" @click="menuOpen = (menuOpen === {{ $project->id }} ? null : {{ $project->id }})" class="-mr-1 rounded p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-700" aria-label="Acties" data-testid="project-actions">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><circle cx="5" cy="12" r="2"/><circle cx="12" cy="12" r="2"/><circle cx="19" cy="12" r="2"/></svg>
                                </button>
                                <div x-show="menuOpen === {{ $project->id }}" x-cloak class="absolute right-0 top-7 z-10 w-40 overflow-hidden rounded-lg border border-gray-200 bg-white py-1 text-sm shadow-lg">
                                    <button type="button" @click="openRename({{ $project->id }}, @js($project->name)); menuOpen = null" class="block w-full px-3 py-1.5 text-left hover:bg-amber-50">Hernoem</button>
                                    <button type="button" @click="openShare({{ $project->id }}); menuOpen = null" class="block w-full px-3 py-1.5 text-left hover:bg-amber-50">Delen</button>
                                    <form method="POST" action="{{ route('projects.duplicate', $project) }}">
                                        @csrf
                                        <button class="block w-full px-3 py-1.5 text-left hover:bg-amber-50">Dupliceer</button>
                                    </form>
                                    <form method="POST" action="{{ route('projects.destroy', $project) }}" onsubmit="return confirm('Project verwijderen?')">
                                        @csrf @method('DELETE')
                                        <button class="block w-full px-3 py-1.5 text-left text-red-600 hover:bg-red-50">Verwijder</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                        <div class="mt-1 flex flex-wrap items-center gap-1.5 text-xs text-gray-500">
                            @if($project->public_permission)
                                <span class="rounded bg-emerald-100 px-1.5 py-0.5 text-emerald-700">Publiek ({{ $project->public_permission === 'write' ? 'lezen+schrijven' : 'alleen lezen' }})</span>
                            @endif
                            @if($project->users->count())
                                <span class="rounded bg-sky-100 px-1.5 py-0.5 text-sky-700">Gedeeld met {{ $project->users->count() }}</span>
                            @endif
                        </div>

                        {{-- Rename dialog --}}
                        <div x-show="renameOpen === {{ $project->id }}" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/40" @keydown.escape.window="closeRename()">
                            <form method="POST" action="{{ route('projects.rename', $project) }}" class="w-full max-w-sm rounded-xl bg-white p-6 shadow-xl" @click.away="closeRename()">
                                @csrf @method('PATCH')
                                <h3 class="mb-3 text-base font-semibold">Project hernoemen</h3>
                                <input type="text" name="name" required maxlength="120" x-model="renameName" x-init="$nextTick(() => { if (renameOpen === {{ $project->id }}) $el.focus(); })" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                                <div class="mt-4 flex justify-end gap-2">
                                    <button type="button" @click="closeRename()" class="rounded-lg px-3 py-1.5 text-sm text-gray-500 hover:bg-gray-100">Annuleer</button>
                                    <button class="rounded-lg bg-amber-500 px-3 py-1.5 text-sm font-medium text-white hover:bg-amber-600">Bewaar</button>
                                </div>
                            </form>
                        </div>

                        {{-- Share dialog --}}
                        <div x-show="shareOpen === {{ $project->id }}" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/40" @keydown.escape.window="closeShare()">
                            <div class="w-full max-w-md rounded-xl bg-white p-6 shadow-xl" @click.away="closeShare()">
                                <h3 class="mb-3 text-base font-semibold">{{ $project->name }} delen</h3>
                                <form method="POST" action="{{ route('projects.share', $project) }}" x-data='shareForm({{ $project->users->map(fn($u) => ["email" => $u->email, "permission" => $u->pivot->permission])->toJson() }}, @json($project->public_permission))' @submit="prepareSubmit()">
                                    @csrf
                                    <input type="hidden" name="public_permission" :value="publicPermission || ''">
                                    <template x-for="(u, i) in users" :key="i">
                                        <div class="mb-2 flex items-center gap-2">
                                            <input :name="`users[${i}][email]`" x-model="u.email" type="email" placeholder="email@voorbeeld.com" class="flex-1 rounded border border-gray-300 px-2 py-1 text-sm">
                                            <select :name="`users[${i}][permission]`" x-model="u.permission" class="rounded border border-gray-300 px-2 py-1 text-sm">
                                                <option value="read">lezen</option>
                                                <option value="write">lezen+schrijven</option>
                                            </select>
                                            <button type="button" @click="users.splice(i,1)" class="text-gray-400 hover:text-red-500">×</button>
                                        </div>
                                    </template>
                                    <button type="button" @click="users.push({email:'',permission:'read'})" class="mb-4 text-xs text-amber-600 hover:underline">+ Gebruiker toevoegen</button>

                                    <div class="mb-4 rounded-lg bg-gray-50 p-3">
                                        <label class="flex cursor-pointer items-center gap-2 text-sm">
                                            <input type="checkbox" x-model="publicEnabled" @change="if(!publicEnabled) publicPermission=''; else publicPermission='read'">
                                            <span class="font-medium">Deel met iedereen</span>
                                        </label>
                                        <div x-show="publicEnabled" x-cloak class="mt-2 flex gap-3 text-sm">
                                            <label class="flex items-center gap-1"><input type="radio" x-model="publicPermission" value="read"> alleen lezen</label>
                                            <label class="flex items-center gap-1"><input type="radio" x-model="publicPermission" value="write"> lezen + schrijven</label>
                                        </div>
                                    </div>

                                    <div class="flex justify-end gap-2">
                                        <button type="button" @click="closeShare()" class="rounded-lg px-3 py-1.5 text-sm text-gray-500 hover:bg-gray-100">Annuleer</button>
                                        <button class="rounded-lg bg-amber-500 px-3 py-1.5 text-sm font-medium text-white hover:bg-amber-600">Bewaar</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </section>

    {{-- Met mij gedeelde projecten --}}
    @if($sharedProjects->count())
        <section class="mb-8" data-testid="shared-section">
            <h2 class="mb-2 text-sm font-semibold uppercase tracking-wider text-gray-500">Met mij gedeeld</h2>
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                @foreach($sharedProjects as $project)
                    <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                        <a href="{{ route('editor', $project) }}" class="text-base font-medium text-gray-900 hover:text-amber-600">{{ $project->name }}</a>
                        <p class="mt-1 text-xs text-gray-500">door {{ $project->owner->name }}</p>
                        <span class="mt-2 inline-block rounded bg-sky-100 px-1.5 py-0.5 text-xs text-sky-700">{{ $project->pivot->permission === 'write' ? 'lezen+schrijven' : 'alleen lezen' }}</span>
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    {{-- Publieke projecten --}}
    @if($publicProjects->count())
        <section data-testid="public-section">
            <h2 class="mb-2 text-sm font-semibold uppercase tracking-wider text-gray-500">Publieke projecten</h2>
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                @foreach($publicProjects as $project)
                    <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                        <a href="{{ route('editor', $project) }}" class="text-base font-medium text-gray-900 hover:text-amber-600">{{ $project->name }}</a>
                        <p class="mt-1 text-xs text-gray-500">door {{ $project->owner->name }}</p>
                        <span class="mt-2 inline-block rounded bg-emerald-100 px-1.5 py-0.5 text-xs text-emerald-700">{{ $project->public_permission === 'write' ? 'lezen+schrijven' : 'alleen lezen' }}</span>
                    </div>
                @endforeach
            </div>
        </section>
    @endif
</div>

<script>
    function projectsPage() {
        return {
            showCreate: false,
            shareOpen: null,
            renameOpen: null,
            renameName: '',
            menuOpen: null,
            openShare(id) { this.shareOpen = id; },
            closeShare() { this.shareOpen = null; },
            openRename(id, currentName) { this.renameOpen = id; this.renameName = currentName; },
            closeRename() { this.renameOpen = null; this.renameName = ''; },
        };
    }

    function shareForm(existing, publicPerm) {
        return {
            users: Array.isArray(existing) ? existing.map(u => ({...u})) : [],
            publicPermission: publicPerm || '',
            publicEnabled: !!publicPerm,
            prepareSubmit() {
                this.users = this.users.filter(u => u.email && u.email.trim() !== '');
            },
        };
    }
</script>

</body>
</html>
