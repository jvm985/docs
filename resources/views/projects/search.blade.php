<x-drive-layout :scope="$scope" :heading="$heading">

    @php($totalCount = $myDrive->count() + $sharedWithMe->count() + $inSharedDrives->count())

    @if($totalCount === 0)
        <div class="rounded-xl border border-dashed border-gray-300 bg-white p-10 text-center" data-testid="no-search-results">
            <p class="text-sm text-gray-500">Geen projecten gevonden voor «{{ $q }}».</p>
        </div>
    @else
        <p class="mb-4 text-sm text-gray-500" data-testid="search-summary">
            {{ $totalCount }} {{ $totalCount === 1 ? 'resultaat' : 'resultaten' }}
        </p>

        @if($myDrive->isNotEmpty())
            <section class="mb-6" data-testid="search-section-my-drive">
                <h2 class="mb-2 text-xs font-semibold uppercase tracking-wider text-gray-500">Mijn Drive</h2>
                <div class="overflow-hidden rounded-xl border border-gray-200 bg-white">
                    <table class="min-w-full text-sm">
                        <tbody class="divide-y divide-gray-100">
                            @foreach($myDrive as $project)
                                <tr class="hover:bg-gray-50" data-testid="search-result-row">
                                    <td class="px-4 py-3">
                                        <a href="{{ route('editor', $project) }}" class="font-medium text-gray-900 hover:text-amber-600">{{ $project->name }}</a>
                                    </td>
                                    <td class="px-4 py-3 text-right text-xs text-gray-500">{{ $project->updated_at?->diffForHumans() }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endif

        @if($sharedWithMe->isNotEmpty())
            <section class="mb-6" data-testid="search-section-shared">
                <h2 class="mb-2 text-xs font-semibold uppercase tracking-wider text-gray-500">Met mij gedeeld</h2>
                <div class="overflow-hidden rounded-xl border border-gray-200 bg-white">
                    <table class="min-w-full text-sm">
                        <tbody class="divide-y divide-gray-100">
                            @foreach($sharedWithMe as $project)
                                <tr class="hover:bg-gray-50" data-testid="search-result-row">
                                    <td class="px-4 py-3">
                                        <a href="{{ route('editor', $project) }}" class="font-medium text-gray-900 hover:text-amber-600">{{ $project->name }}</a>
                                        <span class="ml-2 text-xs text-gray-500">door {{ $project->owner->name }}</span>
                                    </td>
                                    <td class="px-4 py-3 text-right text-xs text-gray-500">{{ $project->updated_at?->diffForHumans() }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endif

        @if($inSharedDrives->isNotEmpty())
            <section class="mb-6" data-testid="search-section-drives">
                <h2 class="mb-2 text-xs font-semibold uppercase tracking-wider text-gray-500">In gedeelde drives</h2>
                <div class="overflow-hidden rounded-xl border border-gray-200 bg-white">
                    <table class="min-w-full text-sm">
                        <tbody class="divide-y divide-gray-100">
                            @foreach($inSharedDrives as $project)
                                <tr class="hover:bg-gray-50" data-testid="search-result-row">
                                    <td class="px-4 py-3">
                                        <a href="{{ route('editor', $project) }}" class="font-medium text-gray-900 hover:text-amber-600">{{ $project->name }}</a>
                                        <span class="ml-2 text-xs text-gray-500">
                                            in <a href="{{ route('drives.show', $project->sharedDrive) }}" class="text-amber-600 hover:underline">{{ $project->sharedDrive->name }}</a>
                                            · door {{ $project->owner->name }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-right text-xs text-gray-500">{{ $project->updated_at?->diffForHumans() }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endif
    @endif
</x-drive-layout>
