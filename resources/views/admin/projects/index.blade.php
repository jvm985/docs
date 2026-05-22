<x-drive-layout :scope="$scope" :heading="$heading">
    <p class="mb-4 text-sm text-gray-500">Alle projecten van alle gebruikers ({{ $projects->count() }}).</p>

    @if($projects->isEmpty())
        <div class="rounded-xl border border-dashed border-gray-300 bg-white p-10 text-center" data-testid="no-projects">
            <p class="text-sm text-gray-500">Nog geen projecten.</p>
        </div>
    @else
        <div class="rounded-xl border border-gray-200 bg-white">
            <table class="min-w-full text-sm" data-testid="admin-projects-table">
                <thead class="bg-gray-50 text-xs uppercase tracking-wider text-gray-500">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium">
                            <x-sort-header key="name" label="Naam" :activeKey="$sortKey" :activeDir="$sortDir" />
                        </th>
                        <th class="px-4 py-3 text-left font-medium">
                            <x-sort-header key="owner" label="Eigenaar" :activeKey="$sortKey" :activeDir="$sortDir" />
                        </th>
                        <th class="px-4 py-3 text-left font-medium">Drive</th>
                        <th class="px-4 py-3 text-left font-medium">
                            <x-sort-header key="public" label="Zichtbaarheid" :activeKey="$sortKey" :activeDir="$sortDir" />
                        </th>
                        <th class="px-4 py-3 text-left font-medium">
                            <x-sort-header key="updated" label="Gewijzigd" :activeKey="$sortKey" :activeDir="$sortDir" />
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($projects as $project)
                        <tr class="hover:bg-gray-50" data-testid="admin-project-row">
                            <td class="px-4 py-3">
                                <a href="{{ route('editor', $project) }}" class="font-medium text-gray-900 hover:text-amber-600" data-testid="admin-project-link">
                                    {{ $project->name }}
                                </a>
                            </td>
                            <td class="px-4 py-3 text-gray-600">
                                @if($project->owner)
                                    <div class="text-gray-900">{{ $project->owner->name }}</div>
                                    <div class="text-xs text-gray-500">{{ $project->owner->email }}</div>
                                @else
                                    <span class="text-gray-400">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-gray-600">
                                @if($project->sharedDrive)
                                    {{ $project->sharedDrive->name }}
                                @else
                                    <span class="text-xs text-gray-400">Mijn Drive</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                @if($project->public_permission)
                                    <span class="rounded bg-emerald-100 px-2 py-0.5 text-xs text-emerald-700">Publiek ({{ $project->public_permission === 'write' ? 'lezen+schrijven' : 'alleen lezen' }})</span>
                                @else
                                    <span class="text-xs text-gray-400">Privé</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-gray-500" title="{{ $project->updated_at?->format('Y-m-d H:i:s') }}">
                                {{ $project->updated_at?->diffForHumans() }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</x-drive-layout>
