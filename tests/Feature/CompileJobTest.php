<?php

use App\Jobs\CompileDocumentJob;
use App\Models\CompileLog;
use App\Models\Node;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->project = Project::factory()->create(['user_id' => $this->user->id]);
    $this->actingAs($this->user);
});

test('compile job is dispatched for latex file', function () {
    Queue::fake();

    $node = Node::factory()->latex()->create(['project_id' => $this->project->id]);

    livewire(\App\Filament\Pages\Editor::class, ['project' => $this->project->id])
        ->set('activeNode', $node)
        ->call('compile');

    Queue::assertPushed(CompileDocumentJob::class, fn ($job) => $job->node->id === $node->id
        && $job->compiler === 'pdflatex');
});

test('compile job is not dispatched for non-compilable file', function () {
    Queue::fake();

    $node = Node::factory()->create([
        'project_id' => $this->project->id,
        'name' => 'data.json',
    ]);

    livewire(\App\Filament\Pages\Editor::class, ['project' => $this->project->id])
        ->set('activeNode', $node)
        ->call('compile');

    Queue::assertNothingPushed();
});

test('compile log success is detected correctly', function () {
    $node = Node::factory()->latex()->create(['project_id' => $this->project->id]);

    $successLog = CompileLog::factory()->create(['node_id' => $node->id, 'user_id' => $this->user->id, 'status' => 'success']);
    $failedLog = CompileLog::factory()->failed()->create(['node_id' => $node->id, 'user_id' => $this->user->id]);

    expect($successLog->isSuccessful())->toBeTrue()
        ->and($failedLog->isSuccessful())->toBeFalse();
});

test('latex node is compilable not executable', function () {
    $node = Node::factory()->latex()->make();
    expect($node->isCompilable())->toBeTrue()
        ->and($node->isExecutable())->toBeFalse();
});

test('markdown node is compilable', function () {
    expect(Node::factory()->markdown()->make()->isCompilable())->toBeTrue();
});

test('r file is executable not compilable', function () {
    $node = Node::factory()->rFile()->make();
    expect($node->isExecutable())->toBeTrue()
        ->and($node->isCompilable())->toBeFalse();
});

test('xelatex compiler is dispatched when selected', function () {
    Queue::fake();

    $node = Node::factory()->latex()->create(['project_id' => $this->project->id]);

    livewire(\App\Filament\Pages\Editor::class, ['project' => $this->project->id])
        ->set('activeNode', $node)
        ->set('activeCompiler', 'xelatex')
        ->call('compile');

    Queue::assertPushed(CompileDocumentJob::class, fn ($job) => $job->compiler === 'xelatex');
});
