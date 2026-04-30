<?php

use App\Models\Node;
use App\Models\Project;
use App\Models\User;

it('loads editor without JS errors', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    Node::factory()->create(['project_id' => $project->id, 'name' => 'test.tex', 'type' => 'file', 'content' => 'hello']);
    $this->actingAs($user);

    visit("/editor/{$project->id}?file=1")
        ->assertNoJavaScriptErrors()
        ->screenshot();
});
