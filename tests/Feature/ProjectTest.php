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

test('owner can delete own project', function () {
    $p = Project::factory()->for($this->user)->create();

    $this->actingAs($this->user)
        ->delete("/projects/{$p->id}")
        ->assertRedirect();

    expect(Project::find($p->id))->toBeNull();
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

test('shared user sees project in their list', function () {
    $other = User::factory()->create();
    $p = Project::factory()->for($this->user)->create(['name' => 'Gedeeld']);
    $p->users()->attach($other->id, ['permission' => 'read']);

    $response = $this->actingAs($other)->get('/projects');
    $response->assertOk()->assertSee('Gedeeld')->assertSee('Met mij gedeeld');
});

test('public project shows in public list for other users', function () {
    $other = User::factory()->create();
    Project::factory()->for($this->user)->create([
        'name' => 'Open Source',
        'public_permission' => 'read',
    ]);

    $this->actingAs($other)->get('/projects')
        ->assertSee('Publieke projecten')
        ->assertSee('Open Source');
});
