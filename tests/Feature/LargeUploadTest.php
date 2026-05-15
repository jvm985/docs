<?php

use App\Models\Project;
use App\Models\SharedDrive;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->teacher = User::factory()->teacher()->create();
    $this->drive = SharedDrive::factory()->for($this->teacher, 'owner')->create();
});

test('owner can init an upload and gets a manifest with chunk plan', function () {
    $resp = $this->actingAs($this->teacher)
        ->postJson("/api/drives/{$this->drive->id}/uploads", [
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
});

test('non-member cannot init an upload', function () {
    $stranger = User::factory()->create();

    $this->actingAs($stranger)
        ->postJson("/api/drives/{$this->drive->id}/uploads", [
            'filename' => 'sneaky.zip',
            'size' => 1024,
        ])
        ->assertForbidden();
});

test('member with read permission cannot init an upload', function () {
    $student = User::factory()->create();
    $this->drive->members()->attach($student, ['permission' => 'read']);

    $this->actingAs($student)
        ->postJson("/api/drives/{$this->drive->id}/uploads", [
            'filename' => 'foo.zip',
            'size' => 1024,
        ])
        ->assertForbidden();
});

test('upload chunks then finish creates a project with the assembled file', function () {
    // Build a file from two predictable chunks (1 KB each).
    $chunkA = str_repeat('A', 1024);
    $chunkB = str_repeat('B', 512);
    $expected = $chunkA.$chunkB;
    $size = strlen($expected);

    $init = $this->actingAs($this->teacher)
        ->postJson("/api/drives/{$this->drive->id}/uploads", [
            'filename' => 'data.bin',
            'size' => $size,
        ])
        ->assertOk()
        ->json();

    $uploadId = $init['upload_id'];

    // Upload chunk 0 (whole file fits in one 5MB chunk)
    $this->actingAs($this->teacher)
        ->call('PUT', "/api/drives/{$this->drive->id}/uploads/{$uploadId}/chunks/0", [], [], [], [], $expected)
        ->assertOk();

    $finish = $this->actingAs($this->teacher)
        ->postJson("/api/drives/{$this->drive->id}/uploads/{$uploadId}/finish")
        ->assertOk()
        ->json();

    expect($finish)->toHaveKey('project_id');

    $project = Project::find($finish['project_id']);
    expect($project)->not->toBeNull();
    expect($project->shared_drive_id)->toBe($this->drive->id);
    expect($project->name)->toBe('data');

    // Verify the file was written to the project's files dir
    $assembled = Storage::disk('local')->get($project->filesPath('data.bin'));
    expect($assembled)->toBe($expected);
});

test('finish refuses if not all chunks received', function () {
    $init = $this->actingAs($this->teacher)
        ->postJson("/api/drives/{$this->drive->id}/uploads", [
            'filename' => 'partial.bin',
            'size' => 12_000_000,
        ])
        ->assertOk()
        ->json();

    // No chunks uploaded
    $this->actingAs($this->teacher)
        ->postJson("/api/drives/{$this->drive->id}/uploads/{$init['upload_id']}/finish")
        ->assertStatus(409);
});

test('status endpoint reflects received chunks (resume support)', function () {
    $init = $this->actingAs($this->teacher)
        ->postJson("/api/drives/{$this->drive->id}/uploads", [
            'filename' => 'big.bin',
            'size' => 6_000_000, // 2 chunks
        ])->json();

    $uploadId = $init['upload_id'];

    // Upload only chunk 1 (the second chunk)
    $body = str_repeat('x', 1024);
    $this->actingAs($this->teacher)
        ->call('PUT', "/api/drives/{$this->drive->id}/uploads/{$uploadId}/chunks/1", [], [], [], [], $body)
        ->assertOk();

    $status = $this->actingAs($this->teacher)
        ->getJson("/api/drives/{$this->drive->id}/uploads/{$uploadId}")
        ->assertOk()
        ->json();

    expect($status['received_chunks'])->toBe([1]);
    expect($status['total_chunks'])->toBe(2);
});

test('cancel deletes upload scratch space', function () {
    $init = $this->actingAs($this->teacher)
        ->postJson("/api/drives/{$this->drive->id}/uploads", [
            'filename' => 'cancel.bin',
            'size' => 1024,
        ])->json();

    $uploadId = $init['upload_id'];

    // Upload a chunk
    $this->actingAs($this->teacher)
        ->call('PUT', "/api/drives/{$this->drive->id}/uploads/{$uploadId}/chunks/0", [], [], [], [], 'data')
        ->assertOk();

    // Cancel
    $this->actingAs($this->teacher)
        ->deleteJson("/api/drives/{$this->drive->id}/uploads/{$uploadId}")
        ->assertOk();

    // Status should now 404
    $this->actingAs($this->teacher)
        ->getJson("/api/drives/{$this->drive->id}/uploads/{$uploadId}")
        ->assertStatus(404);
});

test('chunk index out of range is rejected', function () {
    $init = $this->actingAs($this->teacher)
        ->postJson("/api/drives/{$this->drive->id}/uploads", [
            'filename' => 'x.bin',
            'size' => 1024, // 1 chunk total
        ])->json();

    $this->actingAs($this->teacher)
        ->call('PUT', "/api/drives/{$this->drive->id}/uploads/{$init['upload_id']}/chunks/5", [], [], [], [], 'x')
        ->assertStatus(422);
});

test('invalid upload id format is rejected', function () {
    $this->actingAs($this->teacher)
        ->getJson("/api/drives/{$this->drive->id}/uploads/not-a-valid-id")
        ->assertStatus(422);
});

test('cannot upload to a drive you are not a member of with someone elses upload id', function () {
    $stranger = User::factory()->create();

    $init = $this->actingAs($this->teacher)
        ->postJson("/api/drives/{$this->drive->id}/uploads", [
            'filename' => 'mine.bin',
            'size' => 1024,
        ])->json();

    // Stranger tries to push a chunk to this upload
    $this->actingAs($stranger)
        ->call('PUT', "/api/drives/{$this->drive->id}/uploads/{$init['upload_id']}/chunks/0", [], [], [], [], 'evil')
        ->assertForbidden();
});
