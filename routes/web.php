<?php

use App\Http\Controllers\Api\CompileController;
use App\Http\Controllers\Api\FileController;
use App\Http\Controllers\Api\RController;
use App\Http\Controllers\Auth\SocialiteController;
use App\Http\Controllers\EditorController;
use App\Http\Controllers\ProjectController;
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
    Route::get('/projects', [ProjectController::class, 'index'])->name('projects.index');
    Route::post('/projects', [ProjectController::class, 'store'])->name('projects.store');
    Route::post('/projects/{project}/duplicate', [ProjectController::class, 'duplicate'])->name('projects.duplicate');
    Route::post('/projects/{project}/share', [ProjectController::class, 'share'])->name('projects.share');
    Route::delete('/projects/{project}', [ProjectController::class, 'destroy'])->name('projects.destroy');

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
        Route::post('/copy-from', [FileController::class, 'copyFromOther']);

        Route::post('/compile', [CompileController::class, 'compile']);
        Route::get('/compile/log', [CompileController::class, 'lastLog']);

        Route::post('/r/execute', [RController::class, 'execute']);
        Route::post('/r/reset', [RController::class, 'reset']);
        Route::get('/r/state', [RController::class, 'state']);
    });

    Route::get('/api/my-projects', fn () => response()->json(
        request()->user()->projects()->select('id', 'name')->orderBy('name')->get()
    ));
});
