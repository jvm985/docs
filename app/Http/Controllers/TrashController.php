<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\SharedDrive;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class TrashController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $projects = Project::onlyTrashed()
            ->where(function ($q) use ($user) {
                $q->where('user_id', $user->id)
                    ->orWhereHas('sharedDrive', fn ($d) => $d->where('owner_id', $user->id));
            })
            ->with(['owner', 'sharedDrive'])
            ->orderByDesc('deleted_at')
            ->get();

        $drives = SharedDrive::onlyTrashed()
            ->where('owner_id', $user->id)
            ->orderByDesc('deleted_at')
            ->get();

        return view('trash.index', [
            'trashedProjects' => $projects,
            'trashedDrives' => $drives,
            'scope' => 'trash',
            'heading' => 'Prullenbak',
        ]);
    }

    public function restoreProject(Request $request, int $project)
    {
        $project = Project::onlyTrashed()->findOrFail($project);
        Gate::authorize('restore', $project);
        $project->restore();

        return back();
    }

    public function forceDeleteProject(Request $request, int $project)
    {
        $project = Project::onlyTrashed()->findOrFail($project);
        Gate::authorize('forceDelete', $project);

        $base = storage_path('app/private/projects/'.$project->id);
        $project->forceDelete();
        if (is_dir($base)) {
            $this->rrmdir($base);
        }

        return back();
    }

    public function restoreDrive(Request $request, int $drive)
    {
        $drive = SharedDrive::onlyTrashed()->findOrFail($drive);
        Gate::authorize('restore', $drive);
        $drive->restore();

        return back();
    }

    public function forceDeleteDrive(Request $request, int $drive)
    {
        $drive = SharedDrive::onlyTrashed()->findOrFail($drive);
        Gate::authorize('forceDelete', $drive);

        foreach ($drive->projects()->withTrashed()->get() as $project) {
            $base = storage_path('app/private/projects/'.$project->id);
            $project->forceDelete();
            if (is_dir($base)) {
                $this->rrmdir($base);
            }
        }
        $drive->forceDelete();

        return back();
    }

    private function rrmdir(string $dir): void
    {
        $items = scandir($dir) ?: [];
        foreach ($items as $name) {
            if ($name === '.' || $name === '..') {
                continue;
            }
            $full = $dir.'/'.$name;
            is_dir($full) ? $this->rrmdir($full) : @unlink($full);
        }
        @rmdir($dir);
    }
}
