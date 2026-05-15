<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()->isAdmin(), 403);

        $q = trim((string) $request->query('q', ''));

        $users = User::query()
            ->when($q !== '', fn ($builder) => $builder->where(function ($w) use ($q) {
                $like = '%'.addcslashes($q, '%_\\').'%';
                $w->where('name', 'like', $like)->orWhere('email', 'like', $like);
            }))
            ->orderBy('name')
            ->get();

        return view('admin.users.index', [
            'users' => $users,
            'q' => $q,
            'scope' => 'admin-users',
            'heading' => 'Beheer rollen',
        ]);
    }

    public function updateRole(Request $request, User $user)
    {
        abort_unless($request->user()->isAdmin(), 403);

        $data = $request->validate([
            'role' => ['required', 'in:student,teacher,admin'],
        ]);

        if ($user->id === $request->user()->id && $data['role'] !== 'admin') {
            return back()->withErrors(['role' => 'Je kunt je eigen admin-rol niet wegnemen.']);
        }

        $user->update(['role' => $data['role']]);

        return back();
    }
}
