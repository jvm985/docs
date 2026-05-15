<?php

use App\Models\Project;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
});

test('default sort on my drive is by updated_at desc', function () {
    Project::factory()->for($this->user)->create(['name' => 'Bbb', 'updated_at' => now()->subDays(2)]);
    Project::factory()->for($this->user)->create(['name' => 'Aaa', 'updated_at' => now()]);

    $resp = $this->actingAs($this->user)->get('/projects');
    $body = $resp->getContent();

    expect(strpos($body, 'Aaa'))->toBeLessThan(strpos($body, 'Bbb'));
});

test('sort by name asc on my drive', function () {
    Project::factory()->for($this->user)->create(['name' => 'Zeta']);
    Project::factory()->for($this->user)->create(['name' => 'Alpha']);

    $resp = $this->actingAs($this->user)->get('/projects?sort=name&dir=asc');
    $body = $resp->getContent();

    expect(strpos($body, 'Alpha'))->toBeLessThan(strpos($body, 'Zeta'));
});

test('sort by name desc on my drive', function () {
    Project::factory()->for($this->user)->create(['name' => 'Alpha']);
    Project::factory()->for($this->user)->create(['name' => 'Zeta']);

    $resp = $this->actingAs($this->user)->get('/projects?sort=name&dir=desc');
    $body = $resp->getContent();

    expect(strpos($body, 'Zeta'))->toBeLessThan(strpos($body, 'Alpha'));
});

test('invalid sort key falls back to default', function () {
    Project::factory()->for($this->user)->create(['name' => 'Project A']);

    $this->actingAs($this->user)->get('/projects?sort=evil_column&dir=asc')
        ->assertOk()
        ->assertSee('Project A');
});

test('invalid dir falls back to default', function () {
    Project::factory()->for($this->user)->create(['name' => 'Project B']);

    $this->actingAs($this->user)->get('/projects?sort=name&dir=sideways')
        ->assertOk()
        ->assertSee('Project B');
});

test('shared-with-me supports name sort', function () {
    $owner = User::factory()->create();
    $p1 = Project::factory()->for($owner)->create(['name' => 'Zoo']);
    $p2 = Project::factory()->for($owner)->create(['name' => 'Apple']);
    $p1->users()->attach($this->user, ['permission' => 'read']);
    $p2->users()->attach($this->user, ['permission' => 'read']);

    $resp = $this->actingAs($this->user)->get('/shared?sort=name&dir=asc');
    $body = $resp->getContent();

    expect(strpos($body, 'Apple'))->toBeLessThan(strpos($body, 'Zoo'));
});
