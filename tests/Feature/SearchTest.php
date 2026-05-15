<?php

use App\Models\Project;
use App\Models\SharedDrive;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
});

test('search returns own project from my drive section', function () {
    Project::factory()->for($this->user)->create(['name' => 'Mijn werkstuk']);

    $this->actingAs($this->user)->get('/projects?q=werkstuk')
        ->assertOk()
        ->assertSee('Mijn werkstuk')
        ->assertSee('Mijn Drive');
});

test('search returns shared project from shared section', function () {
    $other = User::factory()->create();
    $p = Project::factory()->for($other)->create(['name' => 'Andermans bestand']);
    $p->users()->attach($this->user, ['permission' => 'read']);

    $this->actingAs($this->user)->get('/projects?q=Andermans')
        ->assertOk()
        ->assertSee('Andermans bestand')
        ->assertSee('Met mij gedeeld');
});

test('search returns drive project from drives section', function () {
    $teacher = User::factory()->teacher()->create();
    $drive = SharedDrive::factory()->for($teacher, 'owner')->create();
    $drive->members()->attach($this->user, ['permission' => 'read']);
    Project::factory()->for($teacher)->create([
        'name' => 'Klasopdracht',
        'shared_drive_id' => $drive->id,
    ]);

    $this->actingAs($this->user)->get('/projects?q=Klasopdracht')
        ->assertOk()
        ->assertSee('Klasopdracht')
        ->assertSee('In gedeelde drives');
});

test('search separates results into three sections', function () {
    $other = User::factory()->create();
    $teacher = User::factory()->teacher()->create();
    $drive = SharedDrive::factory()->for($teacher, 'owner')->create();
    $drive->members()->attach($this->user, ['permission' => 'read']);

    Project::factory()->for($this->user)->create(['name' => 'foo-mijn']);
    $shared = Project::factory()->for($other)->create(['name' => 'foo-gedeeld']);
    $shared->users()->attach($this->user, ['permission' => 'read']);
    Project::factory()->for($teacher)->create([
        'name' => 'foo-drive',
        'shared_drive_id' => $drive->id,
    ]);

    $this->actingAs($this->user)->get('/projects?q=foo')
        ->assertOk()
        ->assertSee('foo-mijn')
        ->assertSee('foo-gedeeld')
        ->assertSee('foo-drive')
        ->assertSee('3 resultaten');
});

test('search shows empty state when no matches', function () {
    Project::factory()->for($this->user)->create(['name' => 'iets anders']);

    $this->actingAs($this->user)->get('/projects?q=onvindbaar')
        ->assertOk()
        ->assertSee('Geen projecten gevonden');
});

test('search does not leak projects from drives where user is not a member', function () {
    $teacher = User::factory()->teacher()->create();
    $drive = SharedDrive::factory()->for($teacher, 'owner')->create();
    Project::factory()->for($teacher)->create([
        'name' => 'GeheimProject',
        'shared_drive_id' => $drive->id,
    ]);

    $this->actingAs($this->user)->get('/projects?q=Geheim')
        ->assertOk()
        ->assertDontSee('GeheimProject');
});

test('empty query falls back to my drive view', function () {
    Project::factory()->for($this->user)->create(['name' => 'Iets']);

    $this->actingAs($this->user)->get('/projects?q=')
        ->assertOk()
        ->assertSee('Mijn Drive')
        ->assertSee('Iets');
});
