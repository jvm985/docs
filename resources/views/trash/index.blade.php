<x-drive-layout :scope="$scope" :heading="$heading">

    @if($trashedProjects->isEmpty() && $trashedDrives->isEmpty())
        <div class="rounded-xl border border-dashed border-gray-300 bg-white p-10 text-center" data-testid="trash-empty">
            <p class="text-sm text-gray-500">Prullenbak is leeg.</p>
        </div>
    @else
        @if($trashedDrives->isNotEmpty())
            <section class="mb-6">
                <h2 class="mb-2 text-sm font-semibold uppercase tracking-wider text-gray-500">Gedeelde drives</h2>
                <div class="overflow-hidden rounded-xl border border-gray-200 bg-white">
                    <table class="min-w-full text-sm" data-testid="trashed-drives-table">
                        <thead class="bg-gray-50 text-xs uppercase tracking-wider text-gray-500">
                            <tr>
                                <th class="px-4 py-3 text-left font-medium">Naam</th>
                                <th class="px-4 py-3 text-left font-medium">Verwijderd</th>
                                <th class="w-48 px-4 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach($trashedDrives as $drive)
                                <tr data-testid="trashed-drive-row">
                                    <td class="px-4 py-3 font-medium text-gray-900">{{ $drive->name }}</td>
                                    <td class="px-4 py-3 text-gray-500">{{ $drive->deleted_at?->diffForHumans() }}</td>
                                    <td class="px-4 py-3 text-right">
                                        <form method="POST" action="{{ route('trash.drives.restore', $drive->id) }}" class="inline">
                                            @csrf
                                            <button class="rounded px-2 py-1 text-xs text-amber-700 hover:bg-amber-50">Herstellen</button>
                                        </form>
                                        <form method="POST" action="{{ route('trash.drives.forceDelete', $drive->id) }}" class="inline" onsubmit="return confirm('Definitief verwijderen? Dit verwijdert ook alle projecten in deze drive.')">
                                            @csrf @method('DELETE')
                                            <button class="rounded px-2 py-1 text-xs text-red-600 hover:bg-red-50">Definitief verwijderen</button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endif

        @if($trashedProjects->isNotEmpty())
            <section>
                <h2 class="mb-2 text-sm font-semibold uppercase tracking-wider text-gray-500">Projecten</h2>
                <div class="overflow-hidden rounded-xl border border-gray-200 bg-white">
                    <table class="min-w-full text-sm" data-testid="trashed-projects-table">
                        <thead class="bg-gray-50 text-xs uppercase tracking-wider text-gray-500">
                            <tr>
                                <th class="px-4 py-3 text-left font-medium">Naam</th>
                                <th class="px-4 py-3 text-left font-medium">Oorspronkelijke locatie</th>
                                <th class="px-4 py-3 text-left font-medium">Verwijderd</th>
                                <th class="w-48 px-4 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach($trashedProjects as $project)
                                <tr data-testid="trashed-project-row">
                                    <td class="px-4 py-3 font-medium text-gray-900">{{ $project->name }}</td>
                                    <td class="px-4 py-3 text-gray-600">{{ $project->sharedDrive?->name ?? 'Mijn Drive' }}</td>
                                    <td class="px-4 py-3 text-gray-500">{{ $project->deleted_at?->diffForHumans() }}</td>
                                    <td class="px-4 py-3 text-right">
                                        <form method="POST" action="{{ route('trash.projects.restore', $project->id) }}" class="inline">
                                            @csrf
                                            <button class="rounded px-2 py-1 text-xs text-amber-700 hover:bg-amber-50">Herstellen</button>
                                        </form>
                                        <form method="POST" action="{{ route('trash.projects.forceDelete', $project->id) }}" class="inline" onsubmit="return confirm('Definitief verwijderen?')">
                                            @csrf @method('DELETE')
                                            <button class="rounded px-2 py-1 text-xs text-red-600 hover:bg-red-50">Definitief verwijderen</button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endif
    @endif
</x-drive-layout>
