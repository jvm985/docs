<?php

use App\Http\Controllers\Admin\ActivityController as AdminActivityController;
use App\Http\Controllers\Admin\ProjectController as AdminProjectController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\Api\CompileController;
use App\Http\Controllers\Api\FileController;
use App\Http\Controllers\Api\LargeUploadController;
use App\Http\Controllers\Api\RController;
use App\Http\Controllers\Auth\SocialiteController;
use App\Http\Controllers\EditorController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\SharedDriveController;
use App\Http\Controllers\TrashController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('projects.index'));

// Testing-only quick login (Pest browser tests). Disabled in production.
if (app()->environment(['testing', 'local'])) {
    Route::get('/__test-login/{userId}', function (int $userId) {
        Auth::loginUsingId($userId);
        $redirect = request()->query('to', '/projects');

        return redirect($redirect);
    });
}

// Auth
Route::middleware('guest')->group(function () {
    Route::view('/login', 'auth.login')->name('login');
    Route::get('/auth/google', [SocialiteController::class, 'redirect'])->name('auth.google');
    Route::get('/auth/google/callback', [SocialiteController::class, 'callback'])->name('auth.google.callback');
});

Route::post('/logout', function () {
    Auth::logout();
    request()->session()->invalidate();
    request()->session()->regenerateToken();

    return redirect('/login');
})->name('logout');

// App
Route::middleware('auth')->group(function () {
    // Info
    Route::view('/info', 'info')->name('info');

    // Mijn Drive
    Route::get('/projects', [ProjectController::class, 'index'])->name('projects.index');
    Route::post('/projects', [ProjectController::class, 'store'])->name('projects.store');
    Route::post('/projects/{project}/duplicate', [ProjectController::class, 'duplicate'])->name('projects.duplicate');
    Route::post('/projects/{project}/share', [ProjectController::class, 'share'])->name('projects.share');
    Route::patch('/projects/{project}/rename', [ProjectController::class, 'rename'])->name('projects.rename');
    Route::delete('/projects/{project}', [ProjectController::class, 'destroy'])->name('projects.destroy');

    // Met mij gedeeld
    Route::get('/shared', [ProjectController::class, 'sharedWithMe'])->name('projects.shared');

    // Gedeelde drives
    Route::get('/drives', [SharedDriveController::class, 'index'])->name('drives.index');
    Route::post('/drives', [SharedDriveController::class, 'store'])->name('drives.store');
    Route::get('/drives/{drive}', [SharedDriveController::class, 'show'])->name('drives.show');
    Route::patch('/drives/{drive}/rename', [SharedDriveController::class, 'rename'])->name('drives.rename');
    Route::post('/drives/{drive}/members', [SharedDriveController::class, 'manageMembers'])->name('drives.members');
    Route::delete('/drives/{drive}', [SharedDriveController::class, 'destroy'])->name('drives.destroy');

    // Beheer (admin only)
    Route::get('/admin/users', [AdminUserController::class, 'index'])->name('admin.users');
    Route::patch('/admin/users/{user}/role', [AdminUserController::class, 'updateRole'])->name('admin.users.role');
    Route::get('/admin/activity', [AdminActivityController::class, 'index'])->name('admin.activity');
    Route::get('/admin/projects', [AdminProjectController::class, 'index'])->name('admin.projects');

    // Prullenbak
    Route::get('/trash', [TrashController::class, 'index'])->name('trash.index');
    Route::post('/trash/projects/{project}/restore', [TrashController::class, 'restoreProject'])->name('trash.projects.restore');
    Route::delete('/trash/projects/{project}', [TrashController::class, 'forceDeleteProject'])->name('trash.projects.forceDelete');
    Route::post('/trash/drives/{drive}/restore', [TrashController::class, 'restoreDrive'])->name('trash.drives.restore');
    Route::delete('/trash/drives/{drive}', [TrashController::class, 'forceDeleteDrive'])->name('trash.drives.forceDelete');

    Route::get('/editor/{project}', [EditorController::class, 'show'])->name('editor');
    Route::get('/editor/{project}/pdf', [EditorController::class, 'pdf'])->name('editor.pdf');
    Route::get('/editor/{project}/plot', [EditorController::class, 'plot'])->name('editor.plot');
    Route::get('/editor/{project}/asset', [EditorController::class, 'asset'])->name('editor.asset');

    Route::prefix('/api/projects/{project}')->group(function () {
        Route::get('/tree', [FileController::class, 'tree']);
        Route::get('/file', [FileController::class, 'read']);
        Route::put('/file', [FileController::class, 'save']);
        Route::post('/file', [FileController::class, 'create']);
        Route::delete('/file', [FileController::class, 'delete']);
        Route::patch('/file/rename', [FileController::class, 'rename']);
        Route::patch('/file/move', [FileController::class, 'move']);
        Route::post('/upload', [FileController::class, 'upload']);
        Route::post('/import', [FileController::class, 'importFromProject']);
        Route::post('/refresh-link', [FileController::class, 'refreshLink']);

        Route::post('/compile', [CompileController::class, 'compile']);
        Route::post('/compile/cancel', [CompileController::class, 'cancel']);
        Route::post('/pdf-locate', [CompileController::class, 'locate']);
        Route::get('/compile/log', [CompileController::class, 'lastLog']);

        Route::patch('/settings', [CompileController::class, 'updateSettings']);

        Route::post('/r/execute', [RController::class, 'execute']);
        Route::post('/r/execute-stream', [RController::class, 'executeStream']);
        Route::post('/r/reset', [RController::class, 'reset']);
        Route::get('/r/state', [RController::class, 'state']);
        Route::post('/r/inspect', [RController::class, 'inspect']);
    });

    // Large file upload into a project (chunked, resumable)
    Route::post('/api/projects/{project}/uploads', [LargeUploadController::class, 'init'])->name('projects.uploads.init');
    Route::get('/api/projects/{project}/uploads/{uploadId}', [LargeUploadController::class, 'status'])->name('projects.uploads.status');
    Route::put('/api/projects/{project}/uploads/{uploadId}/chunks/{index}', [LargeUploadController::class, 'chunk'])
        ->where('index', '[0-9]+')
        ->name('projects.uploads.chunk');
    Route::post('/api/projects/{project}/uploads/{uploadId}/finish', [LargeUploadController::class, 'finish'])->name('projects.uploads.finish');
    Route::delete('/api/projects/{project}/uploads/{uploadId}', [LargeUploadController::class, 'cancel'])->name('projects.uploads.cancel');

    Route::get('/api/my-projects', fn () => response()->json(
        request()->user()->projects()->select('id', 'name')->orderBy('name')->get()
    ));

    Route::get('/api/accessible-projects', [FileController::class, 'accessibleProjects']);
    Route::get('/api/browse-project/{other}', [FileController::class, 'browseProject'])
        ->where('other', '[0-9]+');
});
