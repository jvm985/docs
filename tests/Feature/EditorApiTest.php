<?php

use App\Models\Node;
use App\Models\Project;
use App\Models\User;

it('returns project data with nodes', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    Node::factory()->create(['project_id' => $project->id, 'name' => 'test.tex', 'type' => 'file']);

    $this->actingAs($user)
        ->getJson("/api/editor/{$project->id}")
        ->assertSuccessful()
        ->assertJsonPath('project.name', $project->name)
        ->assertJsonCount(1, 'nodes');
});

it('returns node content', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $node = Node::factory()->create(['project_id' => $project->id, 'name' => 'test.tex', 'type' => 'file', 'content' => 'Hello']);

    $this->actingAs($user)
        ->getJson("/api/editor/{$project->id}/nodes/{$node->id}")
        ->assertSuccessful()
        ->assertJsonPath('content', 'Hello');
});

it('saves node content', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $node = Node::factory()->create(['project_id' => $project->id, 'name' => 'test.tex', 'type' => 'file', 'content' => 'old']);

    $this->actingAs($user)
        ->putJson("/api/editor/{$project->id}/nodes/{$node->id}", ['content' => 'new content'])
        ->assertSuccessful();

    expect($node->fresh()->content)->toBe('new content');
});

it('creates a node', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->postJson("/api/editor/{$project->id}/nodes", ['name' => 'new.tex', 'type' => 'file'])
        ->assertCreated()
        ->assertJsonPath('name', 'new.tex');
});

it('deletes a node', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $node = Node::factory()->create(['project_id' => $project->id, 'type' => 'file']);

    $this->actingAs($user)
        ->deleteJson("/api/editor/{$project->id}/nodes/{$node->id}")
        ->assertSuccessful();

    expect(Node::find($node->id))->toBeNull();
});
