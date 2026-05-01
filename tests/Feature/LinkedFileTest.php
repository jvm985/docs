<?php

use App\Models\Project;
use App\Models\User;
use App\Services\FileService;
use App\Services\LinkRegistry;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->source = Project::factory()->for($this->user)->create(['name' => 'Source']);
    $this->target = Project::factory()->for($this->user)->create(['name' => 'Target']);
    $this->files = app(FileService::class);
    $this->registry = app(LinkRegistry::class);
    $this->files->basePath($this->source);
    $this->files->basePath($this->target);
});

test('importing a file as link marks it linked', function () {
    $this->files->create($this->source, 'template.tex', 'file', 'original');

    $this->actingAs($this->user)
        ->postJson("/api/projects/{$this->target->id}/import", [
            'source_project_id' => $this->source->id,
            'source_path' => 'template.tex',
            'target_parent' => '',
            'mode' => 'link',
        ])
        ->assertOk();

    expect($this->registry->isLinked($this->target, 'template.tex'))->toBeTrue();
});

test('importing as copy does not register a link', function () {
    $this->files->create($this->source, 'template.tex', 'file', 'original');

    $this->actingAs($this->user)
        ->postJson("/api/projects/{$this->target->id}/import", [
            'source_project_id' => $this->source->id,
            'source_path' => 'template.tex',
            'mode' => 'copy',
        ])
        ->assertOk();

    expect($this->registry->isLinked($this->target, 'template.tex'))->toBeFalse();
});

test('saving a linked file is rejected with 423 Locked', function () {
    $this->files->create($this->source, 'template.tex', 'file', 'original');
    $this->actingAs($this->user)
        ->postJson("/api/projects/{$this->target->id}/import", [
            'source_project_id' => $this->source->id,
            'source_path' => 'template.tex',
            'mode' => 'link',
        ]);

    $this->actingAs($this->user)
        ->putJson("/api/projects/{$this->target->id}/file", [
            'path' => 'template.tex',
            'content' => 'tampered',
        ])
        ->assertStatus(423);

    expect(file_get_contents(storage_path('app/private/'.$this->target->filesPath('template.tex'))))
        ->toBe('original');
});

test('refresh-link re-pulls latest content from source', function () {
    $this->files->create($this->source, 'template.tex', 'file', 'v1');
    $this->actingAs($this->user)
        ->postJson("/api/projects/{$this->target->id}/import", [
            'source_project_id' => $this->source->id,
            'source_path' => 'template.tex',
            'mode' => 'link',
        ]);

    // Source file is updated (in real life by its owner)
    $this->files->writeFile($this->source, 'template.tex', 'v2');

    $this->actingAs($this->user)
        ->postJson("/api/projects/{$this->target->id}/refresh-link", ['path' => 'template.tex'])
        ->assertOk();

    expect(file_get_contents(storage_path('app/private/'.$this->target->filesPath('template.tex'))))
        ->toBe('v2');
});

test('refresh on non-linked file returns 422', function () {
    $this->files->create($this->target, 'plain.txt', 'file', 'x');

    $this->actingAs($this->user)
        ->postJson("/api/projects/{$this->target->id}/refresh-link", ['path' => 'plain.txt'])
        ->assertStatus(422);
});

test('importing a folder registers links for every file inside', function () {
    $this->files->create($this->source, 'sub', 'folder');
    $this->files->create($this->source, 'sub/a.txt', 'file', 'A');
    $this->files->create($this->source, 'sub/b.txt', 'file', 'B');

    $this->actingAs($this->user)
        ->postJson("/api/projects/{$this->target->id}/import", [
            'source_project_id' => $this->source->id,
            'source_path' => 'sub',
            'target_parent' => '',
            'mode' => 'link',
        ])
        ->assertOk();

    expect($this->registry->isLinked($this->target, 'sub/a.txt'))->toBeTrue();
    expect($this->registry->isLinked($this->target, 'sub/b.txt'))->toBeTrue();
});

test('tree marks linked files', function () {
    $this->files->create($this->source, 'template.tex', 'file', 'x');
    $this->actingAs($this->user)
        ->postJson("/api/projects/{$this->target->id}/import", [
            'source_project_id' => $this->source->id,
            'source_path' => 'template.tex',
            'mode' => 'link',
        ]);

    $tree = $this->actingAs($this->user)
        ->getJson("/api/projects/{$this->target->id}/tree")
        ->json('tree');

    expect($tree[0]['name'])->toBe('template.tex');
    expect($tree[0]['is_linked'])->toBeTrue();
});

test('accessible-projects lists own + shared + public', function () {
    $other = User::factory()->create();
    $shared = Project::factory()->for($other)->create(['name' => 'Shared']);
    $shared->users()->attach($this->user->id, ['permission' => 'read']);
    Project::factory()->for($other)->create(['name' => 'Pub', 'public_permission' => 'read']);

    $names = collect($this->actingAs($this->user)->getJson('/api/accessible-projects')->json('projects'))
        ->pluck('name')->all();
    expect($names)->toContain('Source')->toContain('Shared')->toContain('Pub');
});

test('browse-project returns the tree of an accessible project', function () {
    $this->files->create($this->source, 'a.tex', 'file', 'x');

    $tree = $this->actingAs($this->user)
        ->getJson("/api/browse-project/{$this->source->id}")
        ->assertOk()
        ->json('tree');

    expect($tree[0]['name'])->toBe('a.tex');
});

test('browse-project rejects projects the user cannot read', function () {
    $other = User::factory()->create();
    $hidden = Project::factory()->for($other)->create();

    $this->actingAs($this->user)
        ->getJson("/api/browse-project/{$hidden->id}")
        ->assertForbidden();
});
