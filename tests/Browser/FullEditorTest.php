<?php

use App\Models\Node;
use App\Models\Project;
use App\Models\User;

it('loads editor with filetree', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    Node::factory()->create(['project_id' => $project->id, 'name' => 'test.tex', 'type' => 'file']);
    $this->actingAs($user);

    $page = visit("/editor/{$project->id}");

    // Dump page source to see what's rendered
    $source = $page->content();
    file_put_contents('/tmp/editor-source.html', $source);

    $page->assertSee('test.tex')
        ->assertNoJavaScriptErrors()
        ->screenshot();
});
