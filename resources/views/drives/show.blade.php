<x-drive-layout :scope="$scope" :heading="$heading" :activeDriveId="$drive->id">
    @php($isOwner = $drive->isOwnedBy(auth()->user()))
    @php($canWrite = $drive->canWrite(auth()->user()))

    <x-slot:actions>
        <div class="flex items-center gap-2" x-data="{ showCreate: false, showMembers: false, showRename: false }">
            @if($canWrite)
                <button type="button" @click="showCreate = true" data-testid="new-project-button" class="rounded-lg bg-amber-500 px-4 py-2 text-sm font-medium text-white hover:bg-amber-600">+ Nieuw project</button>
            @endif
            @if($isOwner)
                <button type="button" @click="showMembers = true" data-testid="manage-members-button" class="rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-700 hover:bg-gray-100">Leden</button>
                <div class="relative" x-data="{ open: false }" @click.away="open = false">
                    <button type="button" @click="open = !open" class="rounded-lg border border-gray-300 px-2 py-2 text-gray-500 hover:bg-gray-100" aria-label="Drive acties">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><circle cx="5" cy="12" r="2"/><circle cx="12" cy="12" r="2"/><circle cx="19" cy="12" r="2"/></svg>
                    </button>
                    <div x-show="open" x-cloak class="absolute right-0 top-10 z-20 w-44 overflow-hidden rounded-lg border border-gray-200 bg-white py-1 text-sm shadow-lg">
                        <button type="button" @click="showRename = true; open = false" class="block w-full px-3 py-1.5 text-left hover:bg-amber-50">Hernoem drive</button>
                        <form method="POST" action="{{ route('drives.destroy', $drive) }}" onsubmit="return confirm('Drive naar prullenbak verplaatsen? Alle projecten gaan mee.')">
                            @csrf @method('DELETE')
                            <button class="block w-full px-3 py-1.5 text-left text-red-600 hover:bg-red-50">Naar prullenbak</button>
                        </form>
                    </div>
                </div>
            @endif

            {{-- Create dialog --}}
            <div x-show="showCreate" x-cloak class="fixed inset-0 z-40 flex items-center justify-center bg-black/40" @keydown.escape.window="showCreate = false">
                <form method="POST" action="{{ route('projects.store') }}" class="w-full max-w-sm rounded-xl bg-white p-6 shadow-xl" @click.away="showCreate = false">
                    @csrf
                    <input type="hidden" name="shared_drive_id" value="{{ $drive->id }}">
                    <h2 class="mb-3 text-lg font-semibold">Nieuw project in {{ $drive->name }}</h2>
                    <input type="text" name="name" required maxlength="120" autofocus class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm" data-testid="new-project-name">
                    <div class="mt-4 flex justify-end gap-2">
                        <button type="button" @click="showCreate = false" class="rounded-lg px-3 py-1.5 text-sm text-gray-500 hover:bg-gray-100">Annuleer</button>
                        <button class="rounded-lg bg-amber-500 px-3 py-1.5 text-sm font-medium text-white hover:bg-amber-600">Aanmaken</button>
                    </div>
                </form>
            </div>

            {{-- Rename dialog --}}
            @if($isOwner)
            <div x-show="showRename" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/40" @keydown.escape.window="showRename = false">
                <form method="POST" action="{{ route('drives.rename', $drive) }}" class="w-full max-w-sm rounded-xl bg-white p-6 shadow-xl" @click.away="showRename = false">
                    @csrf @method('PATCH')
                    <h3 class="mb-3 text-base font-semibold">Drive hernoemen</h3>
                    <input type="text" name="name" required maxlength="120" value="{{ $drive->name }}" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                    <div class="mt-4 flex justify-end gap-2">
                        <button type="button" @click="showRename = false" class="rounded-lg px-3 py-1.5 text-sm text-gray-500 hover:bg-gray-100">Annuleer</button>
                        <button class="rounded-lg bg-amber-500 px-3 py-1.5 text-sm font-medium text-white hover:bg-amber-600">Bewaar</button>
                    </div>
                </form>
            </div>

            {{-- Members dialog --}}
            <div x-show="showMembers" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/40" @keydown.escape.window="showMembers = false">
                <div class="w-full max-w-md rounded-xl bg-white p-6 shadow-xl text-left" @click.away="showMembers = false">
                    <h3 class="mb-3 text-base font-semibold">Leden van {{ $drive->name }}</h3>
                    <form method="POST" action="{{ route('drives.members', $drive) }}" x-data='membersForm({{ $drive->members->map(fn($m) => ["email" => $m->email, "permission" => $m->pivot->permission])->toJson() }})' @submit="prepareSubmit()">
                        @csrf
                        <template x-for="(m, i) in members" :key="i">
                            <div class="mb-2 flex items-center gap-2">
                                <input :name="`members[${i}][email]`" x-model="m.email" type="email" placeholder="email@voorbeeld.com" class="flex-1 rounded border border-gray-300 px-2 py-1 text-sm" data-testid="member-email">
                                <select :name="`members[${i}][permission]`" x-model="m.permission" class="rounded border border-gray-300 px-2 py-1 text-sm">
                                    <option value="read">lezen</option>
                                    <option value="write">lezen+schrijven</option>
                                </select>
                                <button type="button" @click="members.splice(i,1)" class="text-gray-400 hover:text-red-500">×</button>
                            </div>
                        </template>
                        <button type="button" @click="members.push({email:'',permission:'write'})" class="mb-4 text-xs text-amber-600 hover:underline" data-testid="add-member">+ Lid toevoegen</button>
                        <div class="flex justify-end gap-2">
                            <button type="button" @click="showMembers = false" class="rounded-lg px-3 py-1.5 text-sm text-gray-500 hover:bg-gray-100">Annuleer</button>
                            <button class="rounded-lg bg-amber-500 px-3 py-1.5 text-sm font-medium text-white hover:bg-amber-600">Bewaar</button>
                        </div>
                    </form>
                </div>
            </div>
            @endif
        </div>
    </x-slot:actions>

    <div class="mb-4 text-sm text-gray-500">
        Eigenaar: {{ $isOwner ? 'Jij' : $drive->owner->name }} ·
        {{ $drive->members->count() }} {{ $drive->members->count() === 1 ? 'lid' : 'leden' }}
    </div>

    @if($projects->isEmpty())
        <div class="rounded-xl border border-dashed border-gray-300 bg-white p-10 text-center" data-testid="no-drive-projects">
            <p class="text-sm text-gray-500">Nog geen projecten in deze drive.</p>
        </div>
    @else
        <div class="rounded-xl border border-gray-200 bg-white">
            <table class="min-w-full text-sm" data-testid="drive-projects-table">
                <thead class="bg-gray-50 text-xs uppercase tracking-wider text-gray-500">
                    <tr>
                        <th class="px-4 py-2 text-left font-medium">Naam</th>
                        <th class="px-4 py-2 text-left font-medium">Aangemaakt door</th>
                        <th class="px-4 py-2 text-left font-medium">Gewijzigd</th>
                        <th class="w-12 px-2 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100" x-data="{ menuOpen: null }">
                    @foreach($projects as $project)
                        <tr class="hover:bg-gray-50" data-testid="drive-project-row">
                            <td class="px-4 py-2">
                                <a href="{{ route('editor', $project) }}" class="text-gray-900 hover:text-amber-600">{{ $project->name }}</a>
                            </td>
                            <td class="px-4 py-2 text-gray-600">{{ $project->owner->name }}</td>
                            <td class="px-4 py-2 text-gray-500">{{ $project->updated_at?->diffForHumans() }}</td>
                            <td class="px-2 py-2 text-right">
                                @if($canWrite)
                                    <div class="relative inline-block" @click.away="menuOpen = (menuOpen === {{ $project->id }} ? null : menuOpen)">
                                        <button type="button" @click="menuOpen = (menuOpen === {{ $project->id }} ? null : {{ $project->id }})" class="rounded p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-700" aria-label="Acties">
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><circle cx="5" cy="12" r="2"/><circle cx="12" cy="12" r="2"/><circle cx="19" cy="12" r="2"/></svg>
                                        </button>
                                        <div x-show="menuOpen === {{ $project->id }}" x-cloak class="absolute right-0 top-7 z-20 w-44 overflow-hidden rounded-lg border border-gray-200 bg-white py-1 text-sm shadow-lg">
                                            <form method="POST" action="{{ route('projects.duplicate', $project) }}">
                                                @csrf
                                                <button class="block w-full px-3 py-1.5 text-left hover:bg-amber-50">Dupliceer naar Mijn Drive</button>
                                            </form>
                                            <form method="POST" action="{{ route('projects.destroy', $project) }}" onsubmit="return confirm('Naar prullenbak verplaatsen?')">
                                                @csrf @method('DELETE')
                                                <button class="block w-full px-3 py-1.5 text-left text-red-600 hover:bg-red-50">Naar prullenbak</button>
                                            </form>
                                        </div>
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    @push('scripts')
        <script>
            function membersForm(existing) {
                return {
                    members: Array.isArray(existing) ? existing.map(m => ({...m})) : [],
                    prepareSubmit() {
                        this.members = this.members.filter(m => m.email && m.email.trim() !== '');
                    },
                };
            }
        </script>
    @endpush
</x-drive-layout>
