@props(['scope' => 'my-drive', 'activeDriveId' => null])

@php
    $user = auth()->user();
    $myDrives = collect();
    if ($user) {
        $owned = $user->ownedSharedDrives()->orderBy('name')->get();
        $member = $user->sharedDrives()->orderBy('name')->get();
        $myDrives = $owned->concat($member)->unique('id')->sortBy('name')->values();
    }

    // De active-stijl voor sidebar-nav: groene tekst zonder achtergrond.
    // Overleaf-stijl: kleur communiceert active, geen pill.
    $navLink = fn(bool $active) => $active
        ? 'flex items-center gap-2 rounded-md px-3 py-2 font-semibold text-amber-700'
        : 'flex items-center gap-2 rounded-md px-3 py-2 text-gray-700 hover:bg-gray-50';
@endphp

<aside class="w-64 shrink-0 border-r border-gray-200 bg-white">
    <div class="px-5 pt-6 pb-4">
        <button type="button"
                x-data
                @click="$dispatch('open-create-project')"
                data-testid="sidebar-new-project"
                class="w-full rounded-full bg-amber-500 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-amber-600">
            Nieuw project
        </button>
    </div>

    <nav class="px-3 pb-6 text-sm">
        <a href="{{ route('projects.index') }}"
           data-testid="nav-my-drive"
           class="{{ $navLink($scope === 'my-drive') }}">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 7h6l2 2h10v10a2 2 0 0 1-2 2H3z"/></svg>
            <span>Mijn Drive</span>
        </a>

        <a href="{{ route('projects.shared') }}"
           data-testid="nav-shared-with-me"
           class="mt-1 {{ $navLink($scope === 'shared') }}">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 8l4 4-4 4M7 8l-4 4 4 4M14 4l-4 16"/></svg>
            <span>Met mij gedeeld</span>
        </a>

        <div class="mt-1">
            <a href="{{ route('drives.index') }}"
               data-testid="nav-shared-drives"
               class="{{ $navLink($scope === 'shared-drives' && ! $activeDriveId) }}">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 7l9-4 9 4-9 4-9-4zm0 5l9 4 9-4M3 17l9 4 9-4"/></svg>
                <span>Gedeelde drives</span>
            </a>
            @if($myDrives->isNotEmpty())
                <ul class="ml-7 mt-1 space-y-0.5 border-l border-gray-200 pl-2">
                    @foreach($myDrives as $d)
                        <li>
                            <a href="{{ route('drives.show', $d) }}"
                               class="block truncate rounded-md px-2 py-1 text-xs {{ $activeDriveId === $d->id ? 'font-semibold text-amber-700' : 'text-gray-600 hover:bg-gray-50' }}"
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
           class="mt-1 {{ $navLink($scope === 'trash') }}">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2m3 0v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/></svg>
            <span>Prullenbak</span>
        </a>

        @if($user?->isAdmin())
            <div class="mt-6 border-t border-gray-200 pt-3">
                <a href="{{ route('admin.users') }}"
                   data-testid="nav-admin-users"
                   class="{{ $navLink($scope === 'admin-users') }}">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="4"/><path d="M4 22a8 8 0 0 1 16 0"/></svg>
                    <span>Beheer rollen</span>
                </a>
                <a href="{{ route('admin.activity') }}"
                   data-testid="nav-admin-activity"
                   class="mt-1 {{ $navLink($scope === 'admin-activity') }}">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 3"/></svg>
                    <span>Activiteit</span>
                </a>
                <a href="{{ route('admin.projects') }}"
                   data-testid="nav-admin-projects"
                   class="mt-1 {{ $navLink($scope === 'admin-projects') }}">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 7h6l2 2h10v10a2 2 0 0 1-2 2H3z"/></svg>
                    <span>Alle projecten</span>
                </a>
            </div>
        @endif

        <div class="mt-6 border-t border-gray-200 pt-3">
            <a href="{{ route('info') }}"
               data-testid="nav-info"
               class="{{ $navLink(($scope ?? '') === 'info') }}">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 16v-5M12 8h.01"/></svg>
                <span>Over Docs</span>
            </a>
        </div>
    </nav>
</aside>
