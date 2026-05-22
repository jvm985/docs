<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Http\Request;

class ProjectController extends Controller
{
    private const SORTABLE = [
        'name' => 'projects.name',
        'owner' => 'users.name',
        'public' => 'projects.public_permission',
        'updated' => 'projects.updated_at',
    ];

    public function index(Request $request)
    {
        abort_unless($request->user()->isAdmin(), 403);

        $sortKey = $request->query('sort', 'updated');
        if (! array_key_exists($sortKey, self::SORTABLE)) {
            $sortKey = 'updated';
        }
        $dir = strtolower((string) $request->query('dir', 'desc'));
        if (! in_array($dir, ['asc', 'desc'], true)) {
            $dir = 'desc';
        }

        $projects = Project::query()
            ->leftJoin('users', 'users.id', '=', 'projects.user_id')
            ->with(['owner', 'sharedDrive'])
            ->orderBy(self::SORTABLE[$sortKey], $dir)
            ->select('projects.*')
            ->get();

        return view('admin.projects.index', [
            'projects' => $projects,
            'scope' => 'admin-projects',
            'heading' => 'Alle projecten',
            'sortKey' => $sortKey,
            'sortDir' => $dir,
        ]);
    }
}
