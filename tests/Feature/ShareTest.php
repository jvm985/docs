<?php

use App\Filament\Pages\Editor;
use App\Models\Node;
use App\Models\Project;
use App\Models\Share;
use App\Models\User;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->other = User::factory()->create();
    $this->project = Project::factory()->create(['user_id' => $this->owner->id]);
});

test('isSharedWith returns false when not shared', function () {
    expect($this->project->isSharedWith($this->other))->toBeFalse();
});

test('isSharedWith returns true for user-specific share', function () {
    Share::factory()->create([
        'shareable_type' => Project::class,
        'shareable_id' => $this->project->id,
        'user_id' => $this->other->id,
        'permission' => 'read',
    ]);

    expect($this->project->isSharedWith($this->other))->toBeTrue();
});

test('isSharedWith returns true for public share', function () {
    Share::factory()->public()->create([
        'shareable_type' => Project::class,
        'shareable_id' => $this->project->id,
    ]);

    expect($this->project->isSharedWith($this->other))->toBeTrue();
});

test('read-only shared user can view editor', function () {
    Share::factory()->create([
        'shareable_type' => Project::class,
        'shareable_id' => $this->project->id,
        'user_id' => $this->other->id,
        'permission' => 'read',
    ]);

    $this->actingAs($this->other);

    livewire(Editor::class, ['project' => $this->project->id])->assertOk();
});

test('read-only shared user cannot save content', function () {
    Share::factory()->create([
        'shareable_type' => Project::class,
        'shareable_id' => $this->project->id,
        'user_id' => $this->other->id,
        'permission' => 'read',
    ]);
    $node = Node::factory()->create(['project_id' => $this->project->id]);

    $this->actingAs($this->other);

    livewire(Editor::class, ['project' => $this->project->id])
        ->set('activeNode', $node)
        ->call('saveContent', 'hacked')
        ->assertForbidden();
});

test('write-shared user can save content', function () {
    Share::factory()->writable()->create([
        'shareable_type' => Project::class,
        'shareable_id' => $this->project->id,
        'user_id' => $this->other->id,
    ]);
    $node = Node::factory()->create(['project_id' => $this->project->id, 'content' => 'original']);

    $this->actingAs($this->other);

    livewire(Editor::class, ['project' => $this->project->id])
        ->set('activeNode', $node)
        ->call('saveContent', 'updated')
        ->assertHasNoErrors();

    expect($node->refresh()->content)->toBe('updated');
});
