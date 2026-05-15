{{-- Requires Alpine scope with `showCreate` boolean. --}}
<div x-show="showCreate" x-cloak class="fixed inset-0 z-40 flex items-center justify-center bg-black/40" @keydown.escape.window="showCreate = false">
    <form method="POST" action="{{ route('projects.store') }}" class="w-full max-w-sm rounded-xl bg-white p-6 shadow-xl" @click.away="showCreate = false">
        @csrf
        @isset($sharedDriveId)
            <input type="hidden" name="shared_drive_id" value="{{ $sharedDriveId }}">
        @endisset
        <h2 class="mb-3 text-lg font-semibold">Nieuw project</h2>
        <input type="text" name="name" required maxlength="120" autofocus class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm" data-testid="new-project-name" x-init="$watch('showCreate', v => v && $nextTick(() => $el.focus()))">
        <div class="mt-4 flex justify-end gap-2">
            <button type="button" @click="showCreate = false" class="rounded-lg px-3 py-1.5 text-sm text-gray-500 hover:bg-gray-100">Annuleer</button>
            <button class="rounded-lg bg-amber-500 px-3 py-1.5 text-sm font-medium text-white hover:bg-amber-600">Aanmaken</button>
        </div>
    </form>
</div>
