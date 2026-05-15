<x-drive-layout :scope="$scope" :heading="$heading">

    <form method="GET" action="{{ route('admin.users') }}" class="mb-4">
        <div class="relative max-w-md">
            <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
            <input type="search" name="q" value="{{ $q }}" placeholder="Zoek op naam of e-mail..."
                   data-testid="user-search-input"
                   class="w-full rounded-lg border border-gray-200 bg-white py-2 pl-10 pr-4 text-sm shadow-sm focus:border-amber-400 focus:ring-2 focus:ring-amber-200">
        </div>
    </form>

    @if($users->isEmpty())
        <div class="rounded-xl border border-dashed border-gray-300 bg-white p-10 text-center" data-testid="no-users">
            <p class="text-sm text-gray-500">
                @if($q !== '')
                    Geen gebruikers gevonden voor «{{ $q }}».
                @else
                    Nog geen gebruikers.
                @endif
            </p>
        </div>
    @else
        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white">
            <table class="min-w-full text-sm" data-testid="users-table">
                <thead class="bg-gray-50 text-xs uppercase tracking-wider text-gray-500">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium">Naam</th>
                        <th class="px-4 py-3 text-left font-medium">E-mail</th>
                        <th class="px-4 py-3 text-left font-medium">Huidige rol</th>
                        <th class="w-64 px-4 py-3 text-left font-medium">Wijzig rol</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($users as $user)
                        <tr data-testid="user-row">
                            <td class="px-4 py-3 font-medium text-gray-900">{{ $user->name }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ $user->email }}</td>
                            <td class="px-4 py-3">
                                @php($colors = ['student' => 'bg-gray-100 text-gray-700', 'teacher' => 'bg-sky-100 text-sky-700', 'admin' => 'bg-amber-100 text-amber-700'])
                                <span class="rounded px-2 py-0.5 text-xs {{ $colors[$user->role] ?? 'bg-gray-100 text-gray-700' }}" data-testid="user-role-badge">{{ $user->role }}</span>
                            </td>
                            <td class="px-4 py-3">
                                <form method="POST" action="{{ route('admin.users.role', $user) }}" class="flex items-center gap-2">
                                    @csrf @method('PATCH')
                                    <select name="role" class="rounded border border-gray-300 px-2 py-1 text-sm" data-testid="role-select" @disabled($user->id === auth()->id())>
                                        <option value="student" @selected($user->role === 'student')>student</option>
                                        <option value="teacher" @selected($user->role === 'teacher')>teacher</option>
                                        <option value="admin" @selected($user->role === 'admin')>admin</option>
                                    </select>
                                    <button class="rounded bg-amber-500 px-3 py-1 text-xs font-medium text-white hover:bg-amber-600" @disabled($user->id === auth()->id())>Bewaar</button>
                                    @if($user->id === auth()->id())
                                        <span class="text-xs text-gray-400">(jij)</span>
                                    @endif
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</x-drive-layout>
