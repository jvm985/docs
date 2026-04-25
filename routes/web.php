<?php

use App\Http\Controllers\FileController;
use App\Http\Controllers\GoogleAuthController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProjectController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return redirect()->route('login');
});

Route::get('auth/google', [GoogleAuthController::class, 'redirectToGoogle'])->name('google.login');
Route::get('auth/google/callback', [GoogleAuthController::class, 'handleGoogleCallback'])->name('google.callback');

Route::get('/dev-login', function () {
    $user = \App\Models\User::where('email', 'demo@example.com')->first();
    if ($user) {
        auth()->login($user);
        return redirect()->route('dashboard');
    }
    return 'Demo user not found.';
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', function () {
        return redirect()->route('projects.index');
    })->name('dashboard');

    Route::resource('projects', ProjectController::class);
    Route::patch('projects/{project}/share', [ProjectController::class, 'updateSharing'])->name('projects.share.update');

    Route::get('/debug-frontend', function () {
        return Inertia::render('Debug/FrontendTest', [
            'testData' => [
                'type' => 'r',
                'result' => [
                    'structured_output' => [
                        ['type' => 'code', 'content' => 'print("DEBUG_TEST")'],
                        ['type' => 'output', 'content' => '[1] "DEBUG_TEST"'],
                    ],
                    'plots' => [],
                    'variables' => [
                        ['name' => 'x', 'type' => 'numeric', 'value' => '42']
                    ]
                ]
            ]
        ]);
    });
    
    Route::post('files', [FileController::class, 'store'])->name('files.store');
    Route::post('files/upload', [FileController::class, 'upload'])->name('files.upload');
    Route::post('files/{file}/duplicate', [FileController::class, 'duplicate'])->name('files.duplicate');
    Route::post('files/{file}/move', [FileController::class, 'move'])->name('files.move');
    Route::patch('files/{file}', [FileController::class, 'update'])->name('files.update');
    Route::delete('files/{file}', [FileController::class, 'destroy'])->name('files.destroy');
    Route::post('files/{file}/compile', [FileController::class, 'compile'])->name('files.compile');
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
