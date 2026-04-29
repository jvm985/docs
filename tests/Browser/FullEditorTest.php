<?php

use App\Models\Node;
use App\Models\Project;
use App\Models\User;

it('shows CodeMirror with file content when opened via URL', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $node = Node::factory()->create([
        'project_id' => $project->id,
        'name' => 'main.tex',
        'type' => 'file',
        'content' => 'UNIQUE_TEST_CONTENT_HERE',
    ]);
    $this->actingAs($user);

    visit("/editor/{$project->id}?file={$node->id}")
        ->assertSee('main.tex')
        ->assertSee('Compileren')
        ->assertSee('UNIQUE_TEST_CONTENT_HERE')
        ->assertNoJavaScriptErrors()
        ->screenshot();
});
