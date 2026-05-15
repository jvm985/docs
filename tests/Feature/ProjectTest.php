<?php

use App\Models\Project;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
});

test('user can create a project', function () {
    $this->actingAs($this->user)
        ->post('/projects', ['name' => 'My Project'])
        ->assertRedirect();

    expect(Project::where('user_id', $this->user->id)->where('name', 'My Project')->exists())->toBeTrue();
});

test('owner can soft-delete own project (move to trash)', function () {
    $p = Project::factory()->for($this->user)->create();

    $this->actingAs($this->user)
        ->delete("/projects/{$p->id}")
        ->assertRedirect();

    expect(Project::find($p->id))->toBeNull();
    expect(Project::withTrashed()->find($p->id)?->trashed())->toBeTrue();
});

test('non-owner cannot delete project', function () {
    $other = User::factory()->create();
    $p = Project::factory()->for($this->user)->create();

    $this->actingAs($other)
        ->delete("/projects/{$p->id}")
        ->assertForbidden();
});

test('owner can duplicate project', function () {
    $p = Project::factory()->for($this->user)->create(['name' => 'Origineel']);

    $this->actingAs($this->user)
        ->post("/projects/{$p->id}/duplicate")
        ->assertRedirect();

    expect(Project::where('user_id', $this->user->id)->where('name', 'Origineel (kopie)')->exists())->toBeTrue();
});

test('user can share project with another user', function () {
    $other = User::factory()->create(['email' => 'friend@example.com']);
    $p = Project::factory()->for($this->user)->create();

    $this->actingAs($this->user)
        ->post("/projects/{$p->id}/share", [
            'public_permission' => null,
            'users' => [['email' => 'friend@example.com', 'permission' => 'write']],
        ])
        ->assertRedirect();

    expect($p->fresh()->users()->where('users.id', $other->id)->first()->pivot->permission)->toBe('write');
});

test('user can mark project public-readable', function () {
    $p = Project::factory()->for($this->user)->create();

    $this->actingAs($this->user)
        ->post("/projects/{$p->id}/share", ['public_permission' => 'read'])
        ->assertRedirect();

    expect($p->fresh()->public_permission)->toBe('read');
});

test('non-owner cannot share project', function () {
    $other = User::factory()->create();
    $p = Project::factory()->for($this->user)->create();

    $this->actingAs($other)
        ->post("/projects/{$p->id}/share", ['public_permission' => 'read'])
        ->assertForbidden();
});

test('my drive index lists own projects but excludes shared-with-me', function () {
    $own = Project::factory()->for($this->user)->create(['name' => 'Eigen project']);
    $other = User::factory()->create();
    $shared = Project::factory()->for($other)->create(['name' => 'Andermans project']);
    $shared->users()->attach($this->user, ['permission' => 'read']);

    $this->actingAs($this->user)->get('/projects')
        ->assertOk()
        ->assertSee('Mijn Drive')
        ->assertSee('Eigen project')
        ->assertDontSee('Andermans project');
});

test('shared user sees project on /shared', function () {
    $other = User::factory()->create();
    $p = Project::factory()->for($this->user)->create(['name' => 'Gedeeld']);
    $p->users()->attach($other->id, ['permission' => 'read']);

    $this->actingAs($other)->get('/shared')
        ->assertOk()
        ->assertSee('Met mij gedeeld')
        ->assertSee('Gedeeld');
});

test('public project shows in public section for new user', function () {
    $teacher = User::factory()->create();
    Project::factory()->for($teacher)->create([
        'name' => 'Statistiek',
        'public_permission' => 'read',
    ]);

    // Fresh student with no projects at all
    $this->actingAs($this->user)->get('/projects')
        ->assertOk()
        ->assertSee('Statistiek')
        ->assertSee('Publiek toegankelijk');
});

test('public project is NOT shown to its owner in the public section', function () {
    Project::factory()->for($this->user)->create([
        'name' => 'Statistiek',
        'public_permission' => 'read',
    ]);

    $resp = $this->actingAs($this->user)->get('/projects')->assertOk();
    // Statistiek shows once (as own project), not as public
    expect(substr_count($resp->getContent(), 'Publiek toegankelijk'))->toBe(0);
});

test('public project is NOT shown twice if user already has explicit share', function () {
    $teacher = User::factory()->create();
    $p = Project::factory()->for($teacher)->create([
        'name' => 'Statistiek',
        'public_permission' => 'read',
    ]);
    $p->users()->attach($this->user, ['permission' => 'write']);

    $resp = $this->actingAs($this->user)->get('/projects')->assertOk();
    // Statistiek should NOT appear in the public-projects section
    // (it shows on /shared instead, via the explicit pivot)
    expect($resp->getContent())->not->toContain('Publiek toegankelijk');
});

test('public project inside a shared drive is NOT shown in public section', function () {
    $teacher = User::factory()->teacher()->create();
    $drive = \App\Models\SharedDrive::factory()->for($teacher, 'owner')->create();
    Project::factory()->for($teacher)->create([
        'name' => 'DriveProject',
        'shared_drive_id' => $drive->id,
        'public_permission' => 'read',
    ]);

    $this->actingAs($this->user)->get('/projects')
        ->assertOk()
        ->assertDontSee('Publiek toegankelijk');
});

test('private project of someone else is NOT shown', function () {
    $teacher = User::factory()->create();
    Project::factory()->for($teacher)->create([
        'name' => 'Geheim',
        'public_permission' => null,
    ]);

    $this->actingAs($this->user)->get('/projects')
        ->assertOk()
        ->assertDontSee('Geheim');
});

test('trashed project is excluded from my drive index', function () {
    $p = Project::factory()->for($this->user)->create(['name' => 'WeggegooidProject']);
    $p->delete();

    $this->actingAs($this->user)->get('/projects')
        ->assertOk()
        ->assertDontSee('WeggegooidProject');
});
