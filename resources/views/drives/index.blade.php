<x-drive-layout :scope="$scope" :heading="$heading">
    @if(auth()->user()->isTeacher())
        <x-slot:actions>
            <button type="button"
                    x-data
                    @click="$dispatch('open-create-drive')"
                    data-testid="new-drive-button"
                    class="rounded-lg bg-amber-500 px-4 py-2 text-sm font-medium text-white hover:bg-amber-600">
                + Nieuwe gedeelde drive
            </button>
        </x-slot:actions>
    @endif

    <div x-data="drivesIndex()" @open-create-drive.window="showCreate = true">

        {{-- Create dialog --}}
        <div x-show="showCreate" x-cloak class="fixed inset-0 z-40 flex items-center justify-center bg-black/40" @keydown.escape.window="showCreate = false">
            <form method="POST" action="{{ route('drives.store') }}" class="w-full max-w-sm rounded-xl bg-white p-6 shadow-xl" @click.away="showCreate = false">
                @csrf
                <h2 class="mb-3 text-lg font-semibold">Nieuwe gedeelde drive</h2>
                <input type="text" name="name" required maxlength="120" autofocus placeholder="bv. Klas 5B - Wiskunde" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm" data-testid="new-drive-name">
                <div class="mt-4 flex justify-end gap-2">
                    <button type="button" @click="showCreate = false" class="rounded-lg px-3 py-1.5 text-sm text-gray-500 hover:bg-gray-100">Annuleer</button>
                    <button class="rounded-lg bg-amber-500 px-3 py-1.5 text-sm font-medium text-white hover:bg-amber-600">Aanmaken</button>
                </div>
            </form>
        </div>

        @if($drives->isEmpty())
            <div class="rounded-xl border border-dashed border-gray-300 bg-white p-10 text-center" data-testid="no-drives">
                <p class="text-sm text-gray-500">
                    @if(auth()->user()->isTeacher())
                        Nog geen gedeelde drives. Maak er één om met je leerlingen te delen.
                    @else
                        Je bent nog geen lid van een gedeelde drive.
                    @endif
                </p>
            </div>
        @else
            <div class="overflow-hidden rounded-xl border border-gray-200 bg-white">
                <table class="min-w-full text-sm" data-testid="drives-table">
                    <thead class="bg-gray-50 text-xs uppercase tracking-wider text-gray-500">
                        <tr>
                            <th class="px-4 py-3 text-left font-medium">Naam</th>
                            <th class="px-4 py-3 text-left font-medium">Eigenaar</th>
                            <th class="px-4 py-3 text-left font-medium">Aangemaakt</th>
                            <th class="w-12 px-2 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($drives as $drive)
                            @php($isOwner = $drive->owner_id === auth()->id())
                            <tr class="hover:bg-gray-50" data-testid="drive-row">
                                <td class="px-4 py-3">
                                    <a href="{{ route('drives.show', $drive) }}" class="font-medium text-gray-900 hover:text-amber-600">{{ $drive->name }}</a>
                                </td>
                                <td class="px-4 py-3 text-gray-600">{{ $isOwner ? 'Jij' : $drive->owner->name }}</td>
                                <td class="px-4 py-3 text-gray-500">{{ $drive->created_at?->diffForHumans() }}</td>
                                <td class="px-2 py-3 text-right">
                                    @if($isOwner)
                                        <div class="relative inline-block" @click.away="menuOpen = (menuOpen === {{ $drive->id }} ? null : menuOpen)">
                                            <button type="button"
                                                    @click="menuOpen = (menuOpen === {{ $drive->id }} ? null : {{ $drive->id }})"
                                                    class="rounded p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-700"
                                                    aria-label="Drive-acties"
                                                    data-testid="drive-actions">
                                                <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><circle cx="5" cy="12" r="2"/><circle cx="12" cy="12" r="2"/><circle cx="19" cy="12" r="2"/></svg>
                                            </button>
                                            <div x-show="menuOpen === {{ $drive->id }}" x-cloak class="absolute right-0 top-7 z-20 w-48 overflow-hidden rounded-lg border border-gray-200 bg-white py-1 text-sm shadow-lg">
                                                <a href="{{ route('drives.show', $drive) }}" class="block px-3 py-1.5 hover:bg-amber-50">Open</a>
                                                <button type="button" @click="openMembers({{ $drive->id }}); menuOpen = null" class="block w-full px-3 py-1.5 text-left hover:bg-amber-50" data-testid="manage-members-button">Leden beheren…</button>
                                                <form method="POST" action="{{ route('drives.destroy', $drive) }}" onsubmit="return confirm('Drive naar prullenbak verplaatsen? Alle projecten gaan mee.')">
                                                    @csrf @method('DELETE')
                                                    <button class="block w-full px-3 py-1.5 text-left text-red-600 hover:bg-red-50" data-testid="delete-drive-button">Verwijder</button>
                                                </form>
                                            </div>
                                        </div>

                                        {{-- Members dialog --}}
                                        <div x-show="membersOpen === {{ $drive->id }}" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/40" @keydown.escape.window="closeMembers()">
                                            <div class="w-full max-w-md rounded-xl bg-white p-6 shadow-xl text-left" @click.away="closeMembers()">
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
                                                        <button type="button" @click="closeMembers()" class="rounded-lg px-3 py-1.5 text-sm text-gray-500 hover:bg-gray-100">Annuleer</button>
                                                        <button class="rounded-lg bg-amber-500 px-3 py-1.5 text-sm font-medium text-white hover:bg-amber-600">Bewaar</button>
                                                    </div>
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
    </div>

    @push('scripts')
        <script>
            function drivesIndex() {
                return {
                    showCreate: false,
                    menuOpen: null,
                    membersOpen: null,
                    openMembers(id) { this.membersOpen = id; },
                    closeMembers() { this.membersOpen = null; },
                };
            }
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
