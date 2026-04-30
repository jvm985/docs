<?php

use App\Models\Project;
use App\Models\User;
use App\Services\FileService;
use Illuminate\Http\UploadedFile;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->project = Project::factory()->for($this->user)->create();
    $this->files = app(FileService::class);
    $this->files->basePath($this->project);
});

test('tree is empty initially', function () {
    $this->actingAs($this->user)
        ->getJson("/api/projects/{$this->project->id}/tree")
        ->assertOk()
        ->assertJsonPath('tree', []);
});

test('user can create a file', function () {
    $this->actingAs($this->user)
        ->postJson("/api/projects/{$this->project->id}/file", [
            'path' => 'main.tex',
            'type' => 'file',
        ])
        ->assertOk();

    expect(file_exists(storage_path('app/private/'.$this->project->filesPath('main.tex'))))->toBeTrue();
});

test('user can create a folder', function () {
    $this->actingAs($this->user)
        ->postJson("/api/projects/{$this->project->id}/file", [
            'path' => 'chapters',
            'type' => 'folder',
        ])
        ->assertOk();

    expect(is_dir(storage_path('app/private/'.$this->project->filesPath('chapters'))))->toBeTrue();
});

test('user can save and read file content', function () {
    $this->files->create($this->project, 'doc.md', 'file', '# Hello');

    $this->actingAs($this->user)
        ->getJson("/api/projects/{$this->project->id}/file?path=doc.md")
        ->assertOk()
        ->assertJsonPath('kind', 'text')
        ->assertJsonPath('content', '# Hello');

    $this->actingAs($this->user)
        ->putJson("/api/projects/{$this->project->id}/file", ['path' => 'doc.md', 'content' => 'updated'])
        ->assertOk();

    $this->actingAs($this->user)
        ->getJson("/api/projects/{$this->project->id}/file?path=doc.md")
        ->assertJsonPath('content', 'updated');
});

test('user can rename a file', function () {
    $this->files->create($this->project, 'old.txt', 'file', 'x');

    $this->actingAs($this->user)
        ->patchJson("/api/projects/{$this->project->id}/file/rename", ['path' => 'old.txt', 'name' => 'new.txt'])
        ->assertOk()
        ->assertJsonPath('path', 'new.txt');

    expect(file_exists(storage_path('app/private/'.$this->project->filesPath('new.txt'))))->toBeTrue();
});

test('user can move a file into a folder', function () {
    $this->files->create($this->project, 'a.txt', 'file', 'x');
    $this->files->create($this->project, 'sub', 'folder');

    $this->actingAs($this->user)
        ->patchJson("/api/projects/{$this->project->id}/file/move", ['path' => 'a.txt', 'parent' => 'sub'])
        ->assertOk()
        ->assertJsonPath('path', 'sub/a.txt');
});

test('user can delete a file', function () {
    $this->files->create($this->project, 'gone.txt', 'file', 'x');

    $this->actingAs($this->user)
        ->deleteJson("/api/projects/{$this->project->id}/file", ['path' => 'gone.txt'])
        ->assertOk();

    expect(file_exists(storage_path('app/private/'.$this->project->filesPath('gone.txt'))))->toBeFalse();
});

test('upload creates files', function () {
    $this->actingAs($this->user)
        ->post("/api/projects/{$this->project->id}/upload", [
            'folder' => '',
            'files' => [UploadedFile::fake()->createWithContent('hello.txt', 'hi there')],
            'paths' => ['hello.txt'],
        ])
        ->assertOk();

    $abs = storage_path('app/private/'.$this->project->filesPath('hello.txt'));
    expect(file_exists($abs))->toBeTrue();
    expect(file_get_contents($abs))->toBe('hi there');
});

test('upload preserves folder structure', function () {
    $this->actingAs($this->user)
        ->post("/api/projects/{$this->project->id}/upload", [
            'folder' => '',
            'files' => [UploadedFile::fake()->createWithContent('chapter1.tex', 'a')],
            'paths' => ['book/chapter1.tex'],
        ])
        ->assertOk();

    expect(file_exists(storage_path('app/private/'.$this->project->filesPath('book/chapter1.tex'))))->toBeTrue();
});

test('path traversal is blocked', function () {
    $this->actingAs($this->user)
        ->postJson("/api/projects/{$this->project->id}/file", [
            'path' => '../escape.txt',
            'type' => 'file',
        ])
        ->assertStatus(500);
});

test('readonly user cannot save', function () {
    $other = User::factory()->create();
    $this->project->users()->attach($other->id, ['permission' => 'read']);
    $this->files->create($this->project, 'doc.md', 'file', 'x');

    $this->actingAs($other)
        ->putJson("/api/projects/{$this->project->id}/file", ['path' => 'doc.md', 'content' => 'changed'])
        ->assertForbidden();
});

test('readonly user can read tree', function () {
    $other = User::factory()->create();
    $this->project->users()->attach($other->id, ['permission' => 'read']);

    $this->actingAs($other)
        ->getJson("/api/projects/{$this->project->id}/tree")
        ->assertOk();
});

test('outsider cannot read project', function () {
    $other = User::factory()->create();
    $this->actingAs($other)
        ->getJson("/api/projects/{$this->project->id}/tree")
        ->assertForbidden();
});

test('public read project is accessible to outsider', function () {
    $this->project->update(['public_permission' => 'read']);
    $other = User::factory()->create();

    $this->actingAs($other)
        ->getJson("/api/projects/{$this->project->id}/tree")
        ->assertOk();
});

test('user can copy a file from another accessible project', function () {
    $source = Project::factory()->for($this->user)->create();
    $this->files->create($source, 'template.tex', 'file', '\\documentclass{article}');

    $this->actingAs($this->user)
        ->postJson("/api/projects/{$this->project->id}/copy-from", [
            'source_project_id' => $source->id,
            'source_path' => 'template.tex',
            'target_parent' => '',
        ])
        ->assertOk()
        ->assertJsonPath('path', 'template.tex');

    expect(file_exists(storage_path('app/private/'.$this->project->filesPath('template.tex'))))->toBeTrue();
});
