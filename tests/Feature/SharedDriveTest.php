<?php

use App\Models\Project;
use App\Models\SharedDrive;
use App\Models\User;

beforeEach(function () {
    $this->teacher = User::factory()->teacher()->create();
    $this->student = User::factory()->create();
});

test('teacher can create a shared drive', function () {
    $this->actingAs($this->teacher)
        ->post('/drives', ['name' => 'Klas 5B'])
        ->assertRedirect();

    expect(SharedDrive::where('owner_id', $this->teacher->id)->where('name', 'Klas 5B')->exists())->toBeTrue();
});

test('student cannot create a shared drive', function () {
    $this->actingAs($this->student)
        ->post('/drives', ['name' => 'Onmogelijk'])
        ->assertForbidden();

    expect(SharedDrive::where('name', 'Onmogelijk')->exists())->toBeFalse();
});

test('owner can add member to drive', function () {
    $drive = SharedDrive::factory()->for($this->teacher, 'owner')->create();

    $this->actingAs($this->teacher)
        ->post("/drives/{$drive->id}/members", [
            'members' => [['email' => $this->student->email, 'permission' => 'write']],
        ])
        ->assertRedirect();

    expect($drive->fresh()->members()->where('users.id', $this->student->id)->first()->pivot->permission)->toBe('write');
});

test('non-owner cannot manage members', function () {
    $drive = SharedDrive::factory()->for($this->teacher, 'owner')->create();

    $this->actingAs($this->student)
        ->post("/drives/{$drive->id}/members", [
            'members' => [['email' => $this->student->email, 'permission' => 'write']],
        ])
        ->assertForbidden();
});

test('drive member can see drive in their list', function () {
    $drive = SharedDrive::factory()->for($this->teacher, 'owner')->create(['name' => 'Klas 5B']);
    $drive->members()->attach($this->student, ['permission' => 'read']);

    $this->actingAs($this->student)->get('/drives')
        ->assertOk()
        ->assertSee('Klas 5B');
});

test('non-member cannot access drive', function () {
    $drive = SharedDrive::factory()->for($this->teacher, 'owner')->create();

    $this->actingAs($this->student)->get("/drives/{$drive->id}")
        ->assertForbidden();
});

test('drive member with write can create a project in the drive', function () {
    $drive = SharedDrive::factory()->for($this->teacher, 'owner')->create();
    $drive->members()->attach($this->student, ['permission' => 'write']);

    $this->actingAs($this->student)
        ->post('/projects', ['name' => 'Werkstuk', 'shared_drive_id' => $drive->id])
        ->assertRedirect();

    expect(Project::where('shared_drive_id', $drive->id)->where('name', 'Werkstuk')->exists())->toBeTrue();
});

test('drive member with read cannot create a project in the drive', function () {
    $drive = SharedDrive::factory()->for($this->teacher, 'owner')->create();
    $drive->members()->attach($this->student, ['permission' => 'read']);

    $this->actingAs($this->student)
        ->post('/projects', ['name' => 'Niet toegelaten', 'shared_drive_id' => $drive->id])
        ->assertForbidden();
});

test('drive member can read projects in drive', function () {
    $drive = SharedDrive::factory()->for($this->teacher, 'owner')->create();
    $drive->members()->attach($this->student, ['permission' => 'read']);
    $project = Project::factory()->for($this->teacher)->create(['shared_drive_id' => $drive->id]);

    expect($project->canRead($this->student))->toBeTrue();
    expect($project->canWrite($this->student))->toBeFalse();
});

test('drive member with write can write projects in drive', function () {
    $drive = SharedDrive::factory()->for($this->teacher, 'owner')->create();
    $drive->members()->attach($this->student, ['permission' => 'write']);
    $project = Project::factory()->for($this->teacher)->create(['shared_drive_id' => $drive->id]);

    expect($project->canWrite($this->student))->toBeTrue();
});

test('owner can soft-delete drive', function () {
    $drive = SharedDrive::factory()->for($this->teacher, 'owner')->create();

    $this->actingAs($this->teacher)
        ->delete("/drives/{$drive->id}")
        ->assertRedirect();

    expect(SharedDrive::find($drive->id))->toBeNull();
    expect(SharedDrive::withTrashed()->find($drive->id)?->trashed())->toBeTrue();
});

test('share endpoint refuses projects in shared drives', function () {
    $drive = SharedDrive::factory()->for($this->teacher, 'owner')->create();
    $project = Project::factory()->for($this->teacher)->create(['shared_drive_id' => $drive->id]);

    $this->actingAs($this->teacher)
        ->post("/projects/{$project->id}/share", ['public_permission' => 'read'])
        ->assertForbidden();
});
