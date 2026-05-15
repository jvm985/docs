<?php

namespace App\Http\Controllers;

use App\Models\SharedDrive;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class SharedDriveController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $owned = $user->ownedSharedDrives()->orderBy('name')->get();
        $member = $user->sharedDrives()->orderBy('name')->get();

        $drives = $owned->concat($member)->unique('id')->sortBy('name')->values();

        return view('drives.index', [
            'drives' => $drives,
            'scope' => 'shared-drives',
            'heading' => 'Gedeelde drives',
        ]);
    }

    public function store(Request $request)
    {
        Gate::authorize('create', SharedDrive::class);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
        ]);

        $drive = $request->user()->ownedSharedDrives()->create([
            'name' => $data['name'],
        ]);

        return redirect()->route('drives.show', $drive);
    }

    public function show(Request $request, SharedDrive $drive)
    {
        Gate::authorize('view', $drive);

        $drive->load('owner', 'members');
        $projects = $drive->projects()
            ->with('owner')
            ->orderByDesc('updated_at')
            ->get();

        return view('drives.show', [
            'drive' => $drive,
            'projects' => $projects,
            'scope' => 'shared-drives',
            'heading' => $drive->name,
        ]);
    }

    public function rename(Request $request, SharedDrive $drive)
    {
        Gate::authorize('update', $drive);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
        ]);
        $drive->update(['name' => $data['name']]);

        return back();
    }

    public function destroy(Request $request, SharedDrive $drive)
    {
        Gate::authorize('delete', $drive);
        $drive->delete();

        return redirect()->route('drives.index');
    }

    public function manageMembers(Request $request, SharedDrive $drive)
    {
        Gate::authorize('manageMembers', $drive);

        $data = $request->validate([
            'members' => ['array'],
            'members.*.email' => ['required', 'email'],
            'members.*.permission' => ['required', 'in:read,write'],
        ]);

        DB::transaction(function () use ($data, $drive) {
            $sync = [];
            foreach ($data['members'] ?? [] as $entry) {
                $user = User::firstWhere('email', $entry['email']);
                if (! $user || $user->id === $drive->owner_id) {
                    continue;
                }
                $sync[$user->id] = ['permission' => $entry['permission']];
            }
            $drive->members()->sync($sync);
        });

        return back();
    }
}
