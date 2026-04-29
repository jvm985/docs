<?php

use App\Models\Node;
use App\Models\Project;
use App\Models\User;

it('shows the login page with Google button', function () {
    visit('/admin/login')
        ->assertSee('Aanmelden met Google')
        ->screenshot();
});

it('shows the projects page after login', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    visit('/admin/projects')
        ->assertSee('Projecten')
        ->screenshot();
});

it('shows the editor with filetree', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    Node::factory()->create(['project_id' => $project->id, 'name' => 'main.tex', 'type' => 'file', 'content' => '\documentclass{article}']);
    Node::factory()->create(['project_id' => $project->id, 'name' => 'images', 'type' => 'folder']);
    $this->actingAs($user);

    visit("/admin/editor?project={$project->id}")
        ->assertSee('Bestanden')
        ->assertSee('main.tex')
        ->assertSee('images')
        ->screenshot();
});

it('has no javascript errors on editor page', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    Node::factory()->create(['project_id' => $project->id, 'name' => 'main.tex', 'type' => 'file', 'content' => '\documentclass{article}']);
    $this->actingAs($user);

    visit("/admin/editor?project={$project->id}")
        ->assertSee('main.tex')
        ->assertNoJavaScriptErrors();
});
