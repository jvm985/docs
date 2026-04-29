<?php

use App\Http\Controllers\Auth\SocialiteController;
use App\Http\Controllers\EditorApiController;
use App\Http\Controllers\ProjectController;
use App\Models\Project;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('projects.index'));
Route::get('/admin/{any?}', fn () => redirect()->route('projects.index'))->where('any', '.*');

// Auth
Route::get('/login', fn () => view('auth.login'))->name('login')->middleware('guest');
Route::post('/logout', fn () => tap(auth()->logout(), fn () => session()->invalidate()) ?: redirect('/login'))->name('logout');
Route::middleware('guest')->group(function () {
    Route::get('/auth/google', [SocialiteController::class, 'redirect'])->name('auth.google');
    Route::get('/auth/google/callback', [SocialiteController::class, 'callback'])->name('auth.google.callback');
});

// App
Route::middleware('auth')->group(function () {
    Route::get('/projects', [ProjectController::class, 'index'])->name('projects.index');
    Route::post('/projects', [ProjectController::class, 'store'])->name('projects.store');
    Route::post('/projects/{project}/duplicate', [ProjectController::class, 'duplicate'])->name('projects.duplicate');
    Route::post('/projects/{project}/share', [ProjectController::class, 'share'])->name('projects.share');
    Route::delete('/projects/{project}', [ProjectController::class, 'destroy'])->name('projects.destroy');

    Route::get('/api/my-projects', fn () => response()->json(auth()->user()->projects()->select('id', 'name')->get()));

    Route::get('/editor/{project}', fn (Project $project) => view('editor', ['project' => $project]))
        ->name('editor')
        ->can('view', 'project');

    Route::prefix('/api/editor/{project}')->group(function () {
        Route::get('/', [EditorApiController::class, 'project']);
        Route::get('/nodes/{node}', [EditorApiController::class, 'openNode']);
        Route::put('/nodes/{node}', [EditorApiController::class, 'saveNode']);
        Route::post('/nodes', [EditorApiController::class, 'createNode']);
        Route::post('/upload', [EditorApiController::class, 'uploadNodes']);
        Route::delete('/nodes/{node}', [EditorApiController::class, 'deleteNode']);
        Route::patch('/nodes/{node}/rename', [EditorApiController::class, 'renameNode']);
        Route::patch('/nodes/{node}/move', [EditorApiController::class, 'moveNode']);
        Route::post('/nodes/{node}/compile', [EditorApiController::class, 'compile']);
        Route::get('/nodes/{node}/compile-log', [EditorApiController::class, 'compileLog']);
        Route::post('/nodes/{node}/execute-r', [EditorApiController::class, 'executeR']);
        Route::post('/copy-nodes', [EditorApiController::class, 'copyNodesToProject']);
    });
});
