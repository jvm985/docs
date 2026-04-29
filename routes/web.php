<?php

use App\Http\Controllers\Auth\SocialiteController;
use App\Http\Controllers\EditorApiController;
use App\Models\Project;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect('/admin'));

Route::middleware('guest')->group(function () {
    Route::get('/auth/google', [SocialiteController::class, 'redirect'])->name('auth.google');
    Route::get('/auth/google/callback', [SocialiteController::class, 'callback'])->name('auth.google.callback');
});

Route::middleware('auth')->group(function () {
    Route::get('/editor/{project}', fn (Project $project) => view('editor', ['project' => $project]))
        ->name('editor')
        ->can('view', 'project');

    Route::prefix('/api/editor/{project}')->group(function () {
        Route::get('/', [EditorApiController::class, 'project']);
        Route::get('/nodes/{node}', [EditorApiController::class, 'openNode']);
        Route::put('/nodes/{node}', [EditorApiController::class, 'saveNode']);
        Route::post('/nodes', [EditorApiController::class, 'createNode']);
        Route::delete('/nodes/{node}', [EditorApiController::class, 'deleteNode']);
        Route::patch('/nodes/{node}/rename', [EditorApiController::class, 'renameNode']);
        Route::patch('/nodes/{node}/move', [EditorApiController::class, 'moveNode']);
        Route::post('/nodes/{node}/compile', [EditorApiController::class, 'compile']);
        Route::post('/nodes/{node}/execute-r', [EditorApiController::class, 'executeR']);
    });
});
