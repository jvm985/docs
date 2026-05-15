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

    <div x-data="{ showCreate: false }" @open-create-drive.window="showCreate = true">

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
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($drives as $drive)
                            <tr class="hover:bg-gray-50" data-testid="drive-row">
                                <td class="px-4 py-3">
                                    <a href="{{ route('drives.show', $drive) }}" class="font-medium text-gray-900 hover:text-amber-600">{{ $drive->name }}</a>
                                </td>
                                <td class="px-4 py-3 text-gray-600">{{ $drive->owner_id === auth()->id() ? 'Jij' : $drive->owner->name }}</td>
                                <td class="px-4 py-3 text-gray-500">{{ $drive->created_at?->diffForHumans() }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</x-drive-layout>
