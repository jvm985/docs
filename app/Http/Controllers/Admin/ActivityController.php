<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LoginActivity;
use Illuminate\Http\Request;

class ActivityController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()->isAdmin(), 403);

        $activities = LoginActivity::with('user')
            ->orderByDesc('created_at')
            ->limit(100)
            ->get();

        return view('admin.activity.index', [
            'activities' => $activities,
            'scope' => 'admin-activity',
            'heading' => 'Activiteit',
        ]);
    }
}
