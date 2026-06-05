@props(['scope' => 'my-drive', 'activeDriveId' => null])

@php
    $user = auth()->user();
    $myDrives = collect();
    if ($user) {
        $owned = $user->ownedSharedDrives()->orderBy('name')->get();
        $member = $user->sharedDrives()->orderBy('name')->get();
        $myDrives = $owned->concat($member)->unique('id')->sortBy('name')->values();
    }

    // Tekst-only nav, Overleaf-stijl: actief = donker gekleurd + bold,
    // inactief = neutraal slate. Geen background, geen icoon.
    $navLink = fn(bool $active) => $active
        ? 'block rounded px-3 py-1.5 font-semibold text-amber-700'
        : 'block rounded px-3 py-1.5 text-gray-700 hover:bg-gray-100';
@endphp

<aside class="flex w-60 shrink-0 flex-col border-r border-gray-200 bg-white">
    <div class="px-4 pt-5 pb-4">
        <button type="button"
                x-data
                @click="$dispatch('open-create-project')"
                data-testid="sidebar-new-project"
                class="w-full rounded-full bg-amber-500 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-amber-600">
            Nieuw project
        </button>
    </div>

    <nav class="flex-1 px-3 pb-3 text-sm">
        <a href="{{ route('projects.index') }}" data-testid="nav-my-drive" class="{{ $navLink($scope === 'my-drive') }}">
            Mijn projecten
        </a>
        <a href="{{ route('projects.shared') }}" data-testid="nav-shared-with-me" class="{{ $navLink($scope === 'shared') }}">
            Met mij gedeeld
        </a>
        <a href="{{ route('drives.index') }}" data-testid="nav-shared-drives" class="{{ $navLink($scope === 'shared-drives' && ! $activeDriveId) }}">
            Gedeelde drives
        </a>
        @if($myDrives->isNotEmpty())
            <ul class="ml-3 mt-1 space-y-0.5 border-l border-gray-200 pl-2">
                @foreach($myDrives as $d)
                    <li>
                        <a href="{{ route('drives.show', $d) }}"
                           class="block truncate rounded px-2 py-1 text-xs {{ $activeDriveId === $d->id ? 'font-semibold text-amber-700' : 'text-gray-600 hover:bg-gray-100' }}"
                           data-testid="nav-drive-{{ $d->id }}">
                            {{ $d->name }}
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
        <a href="{{ route('trash.index') }}" data-testid="nav-trash" class="{{ $navLink($scope === 'trash') }}">
            Prullenbak
        </a>

        @if($user?->isAdmin())
            <div class="mt-5 pt-3">
                <p class="px-3 pb-2 text-[11px] font-semibold uppercase tracking-widest text-gray-400">Admin</p>
                <a href="{{ route('admin.users') }}" data-testid="nav-admin-users" class="{{ $navLink($scope === 'admin-users') }}">
                    Beheer rollen
                </a>
                <a href="{{ route('admin.activity') }}" data-testid="nav-admin-activity" class="{{ $navLink($scope === 'admin-activity') }}">
                    Activiteit
                </a>
                <a href="{{ route('admin.projects') }}" data-testid="nav-admin-projects" class="{{ $navLink($scope === 'admin-projects') }}">
                    Alle projecten
                </a>
            </div>
        @endif
    </nav>

    <div class="mt-auto flex items-center justify-between border-t border-gray-100 px-4 py-4 text-[11px] font-semibold uppercase tracking-widest text-gray-400">
        <a href="{{ route('info') }}" data-testid="nav-info" class="hover:text-gray-600">Atheneum Kapellen</a>
        <a href="{{ route('info') }}" title="Over Docs" class="text-gray-400 hover:text-gray-600">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="4"/><path d="M4 22a8 8 0 0 1 16 0"/></svg>
        </a>
    </div>
</aside>
