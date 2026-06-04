<x-drive-layout :scope="$scope" :heading="$heading">
    <div x-data="projectsTable()" @open-create-project.window="showCreate = true">

        @include('projects._create_dialog')

        @if($projects->isEmpty())
            <div class="rounded-md border border-dashed border-gray-300 bg-white p-10 text-center" data-testid="no-projects">
                <p class="text-sm text-gray-500">Nog geen projecten in Mijn Drive.</p>
                <button type="button" @click="showCreate = true" class="mt-3 rounded-full bg-amber-500 px-5 py-2 text-sm font-semibold text-white hover:bg-amber-600">Nieuw project</button>
            </div>
        @else
            <div class="overflow-hidden rounded-md border border-gray-200 bg-white">
                <table class="min-w-full text-sm" data-testid="projects-table">
                    <thead class="border-b border-gray-200 bg-white text-xs uppercase tracking-wider text-gray-500">
                        <tr>
                            <th class="px-4 py-2 text-left font-semibold">
                                <x-sort-header key="name" label="Naam" :activeKey="$sortKey ?? null" :activeDir="$sortDir ?? 'desc'" />
                            </th>
                            <th class="px-4 py-2 text-left font-semibold">Gedeeld met</th>
                            <th class="px-4 py-2 text-left font-semibold">
                                <x-sort-header key="public" label="Zichtbaarheid" :activeKey="$sortKey ?? null" :activeDir="$sortDir ?? 'desc'" />
                            </th>
                            <th class="px-4 py-2 text-left font-semibold">
                                <x-sort-header key="updated" label="Gewijzigd" :activeKey="$sortKey ?? null" :activeDir="$sortDir ?? 'desc'" />
                            </th>
                            <th class="w-12 px-2 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($projects as $project)
                            <tr class="hover:bg-gray-50" data-testid="project-row">
                                <td class="px-4 py-2">
                                    <a href="{{ route('editor', $project) }}" class="text-gray-900 hover:text-amber-600" data-testid="project-link">
                                        {{ $project->name }}
                                    </a>
                                </td>
                                <td class="px-4 py-2 text-gray-600">
                                    @if($project->users->isEmpty())
                                        <span class="text-gray-400">—</span>
                                    @else
                                        {{ $project->users->count() }} {{ $project->users->count() === 1 ? 'persoon' : 'personen' }}
                                    @endif
                                </td>
                                <td class="px-4 py-2">
                                    @if($project->public_permission)
                                        <span class="rounded bg-emerald-100 px-2 py-0.5 text-xs text-emerald-700">Publiek ({{ $project->public_permission === 'write' ? 'lezen+schrijven' : 'alleen lezen' }})</span>
                                    @else
                                        <span class="text-xs text-gray-400">Privé</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2 text-gray-500">
                                    {{ $project->updated_at?->diffForHumans() }}
                                </td>
                                <td class="px-2 py-2 text-right">
                                    <div class="relative inline-block" @click.away="menuOpen = (menuOpen === {{ $project->id }} ? null : menuOpen)">
                                        <button type="button"
                                                @click="menuOpen = (menuOpen === {{ $project->id }} ? null : {{ $project->id }})"
                                                class="rounded p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-700"
                                                aria-label="Acties"
                                                data-testid="project-actions">
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><circle cx="5" cy="12" r="2"/><circle cx="12" cy="12" r="2"/><circle cx="19" cy="12" r="2"/></svg>
                                        </button>
                                        <div x-show="menuOpen === {{ $project->id }}" x-cloak class="absolute right-0 top-7 z-20 w-44 overflow-hidden rounded-lg border border-gray-200 bg-white py-1 text-sm shadow-lg">
                                            <button type="button" @click="openRename({{ $project->id }}, @js($project->name)); menuOpen = null" class="block w-full px-3 py-1.5 text-left hover:bg-amber-50">Hernoem</button>
                                            <button type="button" @click="openShare({{ $project->id }}); menuOpen = null" class="block w-full px-3 py-1.5 text-left hover:bg-amber-50">Delen</button>
                                            <form method="POST" action="{{ route('projects.duplicate', $project) }}">
                                                @csrf
                                                <button class="block w-full px-3 py-1.5 text-left hover:bg-amber-50">Dupliceer</button>
                                            </form>
                                            <form method="POST" action="{{ route('projects.destroy', $project) }}" onsubmit="return confirm('Naar prullenbak verplaatsen?')">
                                                @csrf @method('DELETE')
                                                <button class="block w-full px-3 py-1.5 text-left text-red-600 hover:bg-red-50">Naar prullenbak</button>
                                            </form>
                                        </div>
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
                                        <div class="w-full max-w-md rounded-xl bg-white p-6 shadow-xl text-left" @click.away="closeShare()">
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
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        @if(($publicProjects ?? collect())->isNotEmpty())
            <section class="mt-8" data-testid="public-projects-section">
                <h2 class="mb-2 text-xs font-semibold uppercase tracking-wider text-gray-500">Publiek toegankelijk</h2>
                <div class="rounded-xl border border-gray-200 bg-white">
                    <table class="min-w-full text-sm" data-testid="public-projects-table">
                        <thead class="bg-gray-50 text-xs uppercase tracking-wider text-gray-500">
                            <tr>
                                <th class="px-4 py-2 text-left font-medium">Naam</th>
                                <th class="px-4 py-2 text-left font-medium">Eigenaar</th>
                                <th class="px-4 py-2 text-left font-medium">Toegang</th>
                                <th class="px-4 py-2 text-left font-medium">Gewijzigd</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach($publicProjects as $project)
                                <tr class="hover:bg-gray-50" data-testid="public-project-row">
                                    <td class="px-4 py-2">
                                        <a href="{{ route('editor', $project) }}" class="text-gray-900 hover:text-amber-600">{{ $project->name }}</a>
                                    </td>
                                    <td class="px-4 py-2 text-gray-600">{{ $project->owner->name }}</td>
                                    <td class="px-4 py-2">
                                        <span class="rounded bg-emerald-100 px-2 py-0.5 text-xs text-emerald-700">
                                            {{ $project->public_permission === 'write' ? 'lezen+schrijven' : 'alleen lezen' }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-2 text-gray-500">{{ $project->updated_at?->diffForHumans() }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endif
    </div>

    @push('scripts')
        <script>
            function projectsTable() {
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
    @endpush
</x-drive-layout>
