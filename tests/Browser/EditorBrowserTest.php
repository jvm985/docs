<?php

use App\Models\Project;
use App\Models\User;
use App\Services\FileService;

test('login page is rendered', function () {
    $this->visit('/login')
        ->assertSee('Aanmelden met Google');
});

test('projects index loads after login', function () {
    $user = User::factory()->create(['name' => 'Test User']);

    $this->visit("/__test-login/{$user->id}")
        ->assertSee('Projecten')
        ->assertSee('Welkom, Test User');
});

test('projects index shows existing project', function () {
    $user = User::factory()->create();
    Project::factory()->for($user)->create(['name' => 'Existing']);

    $this->visit("/__test-login/{$user->id}")
        ->assertSee('Existing');
});

test('editor opens for an owned project', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create(['name' => 'My Project']);
    $files = app(FileService::class);
    $files->basePath($project);
    $files->create($project, 'main.tex', 'file', '\\documentclass{article}\\begin{document}Hi\\end{document}');

    $this->visit("/__test-login/{$user->id}?to=/editor/{$project->id}")
        ->assertSee('My Project')
        ->assertSee('Bestanden')
        ->assertSee('Selecteer een bestand');
});

test('readonly user sees read-only badge in editor', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $project = Project::factory()->for($owner)->create(['name' => 'Shared']);
    $project->users()->attach($other->id, ['permission' => 'read']);
    app(FileService::class)->basePath($project);

    $this->visit("/__test-login/{$other->id}?to=/editor/{$project->id}")
        ->assertSee('Alleen lezen');
});
