<?php

use App\Filament\Pages\Editor;
use App\Models\Node;
use App\Models\Project;
use App\Models\User;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->project = Project::factory()->create(['user_id' => $this->user->id]);
    $this->actingAs($this->user);
});

test('editor page loads for project owner', function () {
    livewire(Editor::class, ['project' => $this->project->id])->assertOk();
});

test('editor page is forbidden for non-owner without share', function () {
    $other = User::factory()->create();
    $this->actingAs($other);

    livewire(Editor::class, ['project' => $this->project->id])->assertForbidden();
});

test('owner can create a file node', function () {
    livewire(Editor::class, ['project' => $this->project->id])
        ->call('createNode', 'test.md', null, 'file')
        ->assertHasNoErrors();

    expect(Node::where('project_id', $this->project->id)->where('name', 'test.md')->exists())->toBeTrue();
});

test('owner can create a folder node', function () {
    livewire(Editor::class, ['project' => $this->project->id])
        ->call('createNode', 'templates', null, 'folder')
        ->assertHasNoErrors();

    $node = Node::where('project_id', $this->project->id)->where('name', 'templates')->first();
    expect($node)->not->toBeNull()
        ->and($node->type)->toBe('folder');
});

test('owner can delete a node', function () {
    $node = Node::factory()->create(['project_id' => $this->project->id]);

    livewire(Editor::class, ['project' => $this->project->id])
        ->call('deleteNode', $node->id)
        ->assertHasNoErrors();

    expect(Node::find($node->id))->toBeNull();
});

test('owner can rename a node', function () {
    $node = Node::factory()->create(['project_id' => $this->project->id, 'name' => 'old.txt']);

    livewire(Editor::class, ['project' => $this->project->id])
        ->call('renameNode', $node->id, 'new.txt')
        ->assertHasNoErrors();

    expect($node->refresh()->name)->toBe('new.txt');
});

test('owner can move a node to a folder', function () {
    $folder = Node::factory()->folder()->create(['project_id' => $this->project->id]);
    $file = Node::factory()->create(['project_id' => $this->project->id, 'parent_id' => null]);

    livewire(Editor::class, ['project' => $this->project->id])
        ->call('moveNode', $file->id, $folder->id)
        ->assertHasNoErrors();

    expect($file->refresh()->parent_id)->toBe($folder->id);
});

test('owner can save file content', function () {
    $node = Node::factory()->create(['project_id' => $this->project->id, 'content' => 'old content']);

    livewire(Editor::class, ['project' => $this->project->id])
        ->set('activeNode', $node)
        ->call('saveContent', 'new content')
        ->assertHasNoErrors();

    expect($node->refresh()->content)->toBe('new content');
});

test('opening a node sets it as active', function () {
    $node = Node::factory()->create(['project_id' => $this->project->id]);

    livewire(Editor::class, ['project' => $this->project->id])
        ->call('openNode', $node->id)
        ->assertSet('activeNode.id', $node->id);
});

test('node extension detection works correctly', function () {
    expect(Node::factory()->make(['name' => 'doc.tex'])->extension())->toBe('tex')
        ->and(Node::factory()->make(['name' => 'doc.tex'])->isCompilable())->toBeTrue()
        ->and(Node::factory()->make(['name' => 'readme.md'])->isCompilable())->toBeTrue()
        ->and(Node::factory()->make(['name' => 'script.R'])->isExecutable())->toBeTrue()
        ->and(Node::factory()->make(['name' => 'data.json'])->isCompilable())->toBeFalse()
        ->and(Node::factory()->make(['name' => 'data.json'])->isExecutable())->toBeFalse();
});
