<x-drive-layout :scope="$scope" :heading="$heading">

    @if($projects->isEmpty())
        <div class="rounded-xl border border-dashed border-gray-300 bg-white p-10 text-center" data-testid="no-shared-projects">
            <p class="text-sm text-gray-500">Er zijn nog geen projecten met jou gedeeld.</p>
        </div>
    @else
        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white">
            <table class="min-w-full text-sm" data-testid="shared-projects-table">
                <thead class="bg-gray-50 text-xs uppercase tracking-wider text-gray-500">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium">Naam</th>
                        <th class="px-4 py-3 text-left font-medium">Eigenaar</th>
                        <th class="px-4 py-3 text-left font-medium">Toegang</th>
                        <th class="px-4 py-3 text-left font-medium">Gewijzigd</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($projects as $project)
                        <tr class="hover:bg-gray-50" data-testid="shared-project-row">
                            <td class="px-4 py-3">
                                <a href="{{ route('editor', $project) }}" class="font-medium text-gray-900 hover:text-amber-600">{{ $project->name }}</a>
                            </td>
                            <td class="px-4 py-3 text-gray-600">{{ $project->owner->name }}</td>
                            <td class="px-4 py-3">
                                <span class="rounded bg-sky-100 px-2 py-0.5 text-xs text-sky-700">
                                    {{ $project->pivot->permission === 'write' ? 'lezen+schrijven' : 'alleen lezen' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-gray-500">{{ $project->updated_at?->diffForHumans() }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</x-drive-layout>
