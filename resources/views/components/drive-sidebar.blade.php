@props(['scope' => 'my-drive', 'activeDriveId' => null])

@php
    $user = auth()->user();
    $myDrives = collect();
    if ($user) {
        $owned = $user->ownedSharedDrives()->orderBy('name')->get();
        $member = $user->sharedDrives()->orderBy('name')->get();
        $myDrives = $owned->concat($member)->unique('id')->sortBy('name')->values();
    }
@endphp

<aside class="w-64 shrink-0 border-r border-gray-200 bg-white">
    <div class="px-5 py-5">
        <a href="{{ route('projects.index') }}" class="text-lg font-semibold text-gray-900">Docs</a>
        <p class="mt-0.5 text-xs text-gray-500">{{ $user?->name }}</p>
    </div>

    <nav class="px-2 pb-6 text-sm">
        <a href="{{ route('projects.index') }}"
           data-testid="nav-my-drive"
           class="flex items-center gap-2 rounded-lg px-3 py-2 {{ $scope === 'my-drive' ? 'bg-amber-50 font-medium text-amber-700' : 'text-gray-700 hover:bg-gray-100' }}">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 7h6l2 2h10v10a2 2 0 0 1-2 2H3z"/></svg>
            <span>Mijn Drive</span>
        </a>

        <a href="{{ route('projects.shared') }}"
           data-testid="nav-shared-with-me"
           class="mt-1 flex items-center gap-2 rounded-lg px-3 py-2 {{ $scope === 'shared' ? 'bg-amber-50 font-medium text-amber-700' : 'text-gray-700 hover:bg-gray-100' }}">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 8l4 4-4 4M7 8l-4 4 4 4M14 4l-4 16"/></svg>
            <span>Met mij gedeeld</span>
        </a>

        <div class="mt-3">
            <a href="{{ route('drives.index') }}"
               data-testid="nav-shared-drives"
               class="flex items-center gap-2 rounded-lg px-3 py-2 {{ $scope === 'shared-drives' && ! $activeDriveId ? 'bg-amber-50 font-medium text-amber-700' : 'text-gray-700 hover:bg-gray-100' }}">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 7l9-4 9 4-9 4-9-4zm0 5l9 4 9-4M3 17l9 4 9-4"/></svg>
                <span>Gedeelde drives</span>
            </a>
            @if($myDrives->isNotEmpty())
                <ul class="ml-6 mt-1 space-y-0.5 border-l border-gray-200 pl-2">
                    @foreach($myDrives as $d)
                        <li>
                            <a href="{{ route('drives.show', $d) }}"
                               class="block truncate rounded-md px-2 py-1 text-xs {{ $activeDriveId === $d->id ? 'bg-amber-100 font-medium text-amber-700' : 'text-gray-600 hover:bg-gray-100' }}"
                               data-testid="nav-drive-{{ $d->id }}">
                                {{ $d->name }}
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        <a href="{{ route('trash.index') }}"
           data-testid="nav-trash"
           class="mt-3 flex items-center gap-2 rounded-lg px-3 py-2 {{ $scope === 'trash' ? 'bg-amber-50 font-medium text-amber-700' : 'text-gray-700 hover:bg-gray-100' }}">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2m3 0v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/></svg>
            <span>Prullenbak</span>
        </a>
    </nav>
</aside>
