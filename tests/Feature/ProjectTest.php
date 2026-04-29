<?php

use App\Filament\Resources\Projects\Pages\ManageProjects;
use App\Models\Project;
use App\Models\User;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

test('projects page loads for authenticated user', function () {
    livewire(ManageProjects::class)->assertOk();
});

test('user only sees their own projects in the table', function () {
    Project::factory()->create(['user_id' => $this->user->id, 'name' => 'My Project']);
    Project::factory()->create(['name' => 'Other Project']);

    livewire(ManageProjects::class)
        ->assertSee('My Project')
        ->assertDontSee('Other Project');
});

test('user can create a project', function () {
    livewire(ManageProjects::class)
        ->callAction('create', data: ['name' => 'New Project'])
        ->assertHasNoActionErrors();

    expect(Project::where('name', 'New Project')->where('user_id', $this->user->id)->exists())->toBeTrue();
});

test('project name is required', function () {
    livewire(ManageProjects::class)
        ->callAction('create', data: ['name' => ''])
        ->assertHasActionErrors(['name' => 'required']);
});

test('user can delete their own project', function () {
    $project = Project::factory()->create(['user_id' => $this->user->id]);

    livewire(ManageProjects::class)
        ->callTableAction('delete', $project)
        ->assertHasNoErrors();

    expect(Project::find($project->id))->toBeNull();
});
