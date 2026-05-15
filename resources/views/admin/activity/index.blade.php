<x-drive-layout :scope="$scope" :heading="$heading">
    <p class="mb-4 text-sm text-gray-500">Laatste 100 logins (meest recent bovenaan).</p>

    @if($activities->isEmpty())
        <div class="rounded-xl border border-dashed border-gray-300 bg-white p-10 text-center" data-testid="no-activity">
            <p class="text-sm text-gray-500">Nog geen logins geregistreerd.</p>
        </div>
    @else
        <div class="rounded-xl border border-gray-200 bg-white">
            <table class="min-w-full text-sm" data-testid="activity-table">
                <thead class="bg-gray-50 text-xs uppercase tracking-wider text-gray-500">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium">Gebruiker</th>
                        <th class="px-4 py-3 text-left font-medium">E-mail</th>
                        <th class="px-4 py-3 text-left font-medium">IP</th>
                        <th class="px-4 py-3 text-left font-medium">Browser</th>
                        <th class="px-4 py-3 text-left font-medium">Wanneer</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($activities as $a)
                        <tr data-testid="activity-row">
                            <td class="px-4 py-3 font-medium text-gray-900">{{ $a->user?->name ?? '—' }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ $a->user?->email ?? '—' }}</td>
                            <td class="px-4 py-3 text-gray-500">{{ $a->ip_address ?? '—' }}</td>
                            <td class="px-4 py-3 max-w-xs truncate text-xs text-gray-500" title="{{ $a->user_agent }}">{{ $a->user_agent ?? '—' }}</td>
                            <td class="px-4 py-3 text-gray-500" title="{{ $a->created_at?->format('Y-m-d H:i:s') }}">{{ $a->created_at?->diffForHumans() }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</x-drive-layout>
