<?php

use App\Models\Node;
use App\Models\Project;
use App\Models\User;

it('loads editor with filetree and no JS errors', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    Node::factory()->create(['project_id' => $project->id, 'name' => 'test.tex', 'type' => 'file']);
    Node::factory()->create(['project_id' => $project->id, 'name' => 'docs', 'type' => 'folder']);
    $this->actingAs($user);

    visit("/editor/{$project->id}")
        ->assertSee('test.tex')
        ->assertSee('docs')
        ->assertSee('Bestanden')
        ->assertSee('Projecten')
        ->assertNoJavaScriptErrors()
        ->screenshot();
});
