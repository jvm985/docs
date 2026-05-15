<?php

use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->project = Project::factory()->for($this->user)->create();
});

test('owner can init an upload and gets a manifest', function () {
    $resp = $this->actingAs($this->user)
        ->postJson("/api/projects/{$this->project->id}/uploads", [
            'filename' => 'big.mp4',
            'size' => 12_000_000, // 12 MB → 3 chunks of 5 MB
        ])
        ->assertOk()
        ->json();

    expect($resp)->toHaveKeys(['upload_id', 'chunk_size', 'total_chunks', 'received_chunks']);
    expect($resp['chunk_size'])->toBe(5 * 1024 * 1024);
    expect($resp['total_chunks'])->toBe(3);
    expect($resp['received_chunks'])->toBe([]);
    expect($resp['filename'])->toBe('big.mp4');
    expect($resp['project_id'])->toBe($this->project->id);
});

test('non-owner without share access cannot init an upload', function () {
    $stranger = User::factory()->create();

    $this->actingAs($stranger)
        ->postJson("/api/projects/{$this->project->id}/uploads", [
            'filename' => 'sneaky.zip',
            'size' => 1024,
        ])
        ->assertForbidden();
});

test('shared user with read permission cannot init an upload', function () {
    $reader = User::factory()->create();
    $this->project->users()->attach($reader, ['permission' => 'read']);

    $this->actingAs($reader)
        ->postJson("/api/projects/{$this->project->id}/uploads", [
            'filename' => 'foo.zip',
            'size' => 1024,
        ])
        ->assertForbidden();
});

test('shared user with write permission can init an upload', function () {
    $writer = User::factory()->create();
    $this->project->users()->attach($writer, ['permission' => 'write']);

    $this->actingAs($writer)
        ->postJson("/api/projects/{$this->project->id}/uploads", [
            'filename' => 'foo.zip',
            'size' => 1024,
        ])->assertOk();
});

test('upload chunks then finish writes the file into the project', function () {
    $chunkA = str_repeat('A', 1024);
    $chunkB = str_repeat('B', 512);
    $expected = $chunkA.$chunkB;
    $size = strlen($expected);

    $init = $this->actingAs($this->user)
        ->postJson("/api/projects/{$this->project->id}/uploads", [
            'filename' => 'data.bin',
            'size' => $size,
        ])
        ->assertOk()
        ->json();

    $uploadId = $init['upload_id'];

    $this->actingAs($this->user)
        ->call('PUT', "/api/projects/{$this->project->id}/uploads/{$uploadId}/chunks/0", [], [], [], [], $expected)
        ->assertOk();

    $finish = $this->actingAs($this->user)
        ->postJson("/api/projects/{$this->project->id}/uploads/{$uploadId}/finish")
        ->assertOk()
        ->json();

    expect($finish['project_id'])->toBe($this->project->id);
    expect($finish['filename'])->toBe('data.bin');

    $assembled = Storage::disk('local')->get($this->project->filesPath('data.bin'));
    expect($assembled)->toBe($expected);
});

test('finish refuses if not all chunks received', function () {
    $init = $this->actingAs($this->user)
        ->postJson("/api/projects/{$this->project->id}/uploads", [
            'filename' => 'partial.bin',
            'size' => 12_000_000,
        ])->json();

    $this->actingAs($this->user)
        ->postJson("/api/projects/{$this->project->id}/uploads/{$init['upload_id']}/finish")
        ->assertStatus(409);
});

test('status endpoint reflects received chunks (resume support)', function () {
    $init = $this->actingAs($this->user)
        ->postJson("/api/projects/{$this->project->id}/uploads", [
            'filename' => 'big.bin',
            'size' => 6_000_000,
        ])->json();

    $uploadId = $init['upload_id'];

    $body = str_repeat('x', 1024);
    $this->actingAs($this->user)
        ->call('PUT', "/api/projects/{$this->project->id}/uploads/{$uploadId}/chunks/1", [], [], [], [], $body)
        ->assertOk();

    $status = $this->actingAs($this->user)
        ->getJson("/api/projects/{$this->project->id}/uploads/{$uploadId}")
        ->assertOk()
        ->json();

    expect($status['received_chunks'])->toBe([1]);
    expect($status['total_chunks'])->toBe(2);
});

test('cancel deletes upload scratch space', function () {
    $init = $this->actingAs($this->user)
        ->postJson("/api/projects/{$this->project->id}/uploads", [
            'filename' => 'cancel.bin',
            'size' => 1024,
        ])->json();

    $uploadId = $init['upload_id'];

    $this->actingAs($this->user)
        ->call('PUT', "/api/projects/{$this->project->id}/uploads/{$uploadId}/chunks/0", [], [], [], [], 'data')
        ->assertOk();

    $this->actingAs($this->user)
        ->deleteJson("/api/projects/{$this->project->id}/uploads/{$uploadId}")
        ->assertOk();

    $this->actingAs($this->user)
        ->getJson("/api/projects/{$this->project->id}/uploads/{$uploadId}")
        ->assertStatus(404);
});

test('chunk index out of range is rejected', function () {
    $init = $this->actingAs($this->user)
        ->postJson("/api/projects/{$this->project->id}/uploads", [
            'filename' => 'x.bin',
            'size' => 1024,
        ])->json();

    $this->actingAs($this->user)
        ->call('PUT', "/api/projects/{$this->project->id}/uploads/{$init['upload_id']}/chunks/5", [], [], [], [], 'x')
        ->assertStatus(422);
});

test('invalid upload id format is rejected', function () {
    $this->actingAs($this->user)
        ->getJson("/api/projects/{$this->project->id}/uploads/not-a-valid-id")
        ->assertStatus(422);
});

test('a different user cannot push a chunk to someone elses upload', function () {
    $stranger = User::factory()->create();

    $init = $this->actingAs($this->user)
        ->postJson("/api/projects/{$this->project->id}/uploads", [
            'filename' => 'mine.bin',
            'size' => 1024,
        ])->json();

    $this->actingAs($stranger)
        ->call('PUT', "/api/projects/{$this->project->id}/uploads/{$init['upload_id']}/chunks/0", [], [], [], [], 'evil')
        ->assertForbidden();
});

test('upload also works on a project that lives inside a shared drive', function () {
    $teacher = User::factory()->teacher()->create();
    $drive = \App\Models\SharedDrive::factory()->for($teacher, 'owner')->create();
    $student = User::factory()->create();
    $drive->members()->attach($student, ['permission' => 'write']);

    $project = Project::factory()->for($teacher)->create(['shared_drive_id' => $drive->id]);

    $this->actingAs($student)
        ->postJson("/api/projects/{$project->id}/uploads", [
            'filename' => 'data.bin',
            'size' => 1024,
        ])
        ->assertOk();
});
