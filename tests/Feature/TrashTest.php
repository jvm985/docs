<?php

use App\Models\Project;
use App\Models\SharedDrive;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
});

test('trashed project appears in trash for owner', function () {
    $p = Project::factory()->for($this->user)->create(['name' => 'WegMij']);
    $p->delete();

    $this->actingAs($this->user)->get('/trash')
        ->assertOk()
        ->assertSee('Prullenbak')
        ->assertSee('WegMij');
});

test('owner can restore project from trash', function () {
    $p = Project::factory()->for($this->user)->create();
    $p->delete();

    $this->actingAs($this->user)
        ->post("/trash/projects/{$p->id}/restore")
        ->assertRedirect();

    expect(Project::find($p->id))->not->toBeNull();
    expect(Project::find($p->id)->trashed())->toBeFalse();
});

test('owner can permanently delete project from trash', function () {
    $p = Project::factory()->for($this->user)->create();
    $p->delete();

    $this->actingAs($this->user)
        ->delete("/trash/projects/{$p->id}")
        ->assertRedirect();

    expect(Project::withTrashed()->find($p->id))->toBeNull();
});

test('non-owner cannot see other user trashed projects', function () {
    $other = User::factory()->create();
    $p = Project::factory()->for($other)->create(['name' => 'Anders']);
    $p->delete();

    $this->actingAs($this->user)->get('/trash')
        ->assertOk()
        ->assertDontSee('Anders');
});

test('non-owner cannot restore other user project', function () {
    $other = User::factory()->create();
    $p = Project::factory()->for($other)->create();
    $p->delete();

    $this->actingAs($this->user)
        ->post("/trash/projects/{$p->id}/restore")
        ->assertForbidden();
});

test('teacher can restore trashed drive', function () {
    $teacher = User::factory()->teacher()->create();
    $drive = SharedDrive::factory()->for($teacher, 'owner')->create();
    $drive->delete();

    $this->actingAs($teacher)
        ->post("/trash/drives/{$drive->id}/restore")
        ->assertRedirect();

    expect(SharedDrive::find($drive->id))->not->toBeNull();
});

test('drive owner sees trashed projects from their drives', function () {
    $teacher = User::factory()->teacher()->create();
    $drive = SharedDrive::factory()->for($teacher, 'owner')->create();
    $p = Project::factory()->for($this->user)->create([
        'shared_drive_id' => $drive->id,
        'name' => 'DriveProject',
    ]);
    $p->delete();

    $this->actingAs($teacher)->get('/trash')
        ->assertOk()
        ->assertSee('DriveProject');
});
