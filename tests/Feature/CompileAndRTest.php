<?php

use App\Models\Project;
use App\Models\User;
use App\Services\FileService;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->project = Project::factory()->for($this->user)->create();
    $this->files = app(FileService::class);
    $this->files->basePath($this->project);
});

test('latex compile produces a pdf when binaries are available', function () {
    if (! commandExists('pdflatex')) {
        $this->markTestSkipped('pdflatex not installed');
    }

    $this->files->create($this->project, 'main.tex', 'file', "\\documentclass{article}\n\\begin{document}Hello\\end{document}\n");

    $response = $this->actingAs($this->user)
        ->postJson("/api/projects/{$this->project->id}/compile", [
            'path' => 'main.tex',
            'compiler' => 'pdflatex',
        ])
        ->assertOk();

    expect($response->json('status'))->toBe('success');
    expect($response->json('pdf_url'))->not->toBeNull();
});

test('typst compile produces a pdf when binaries are available', function () {
    if (! commandExists('typst')) {
        $this->markTestSkipped('typst not installed');
    }

    $this->files->create($this->project, 'main.typ', 'file', "= Hello\nWereld.\n");

    $response = $this->actingAs($this->user)
        ->postJson("/api/projects/{$this->project->id}/compile", ['path' => 'main.typ'])
        ->assertOk();

    expect($response->json('status'))->toBe('success');
});

test('markdown compile produces a pdf when binaries are available', function () {
    if (! commandExists('pandoc') || ! commandExists('xelatex')) {
        $this->markTestSkipped('pandoc/xelatex not installed');
    }

    $this->files->create($this->project, 'doc.md', 'file', "# Hi\n\nText.\n");

    $response = $this->actingAs($this->user)
        ->postJson("/api/projects/{$this->project->id}/compile", ['path' => 'doc.md'])
        ->assertOk();

    expect($response->json('status'))->toBe('success');
});

test('R execute returns code and output entries when Rscript is available', function () {
    if (! commandExists('Rscript')) {
        $this->markTestSkipped('Rscript not installed');
    }

    $response = $this->actingAs($this->user)
        ->postJson("/api/projects/{$this->project->id}/r/execute", [
            'code' => "x <- 1 + 1\nprint(x)",
        ])
        ->assertOk();

    $output = $response->json('output');
    expect($output)->toBeArray();
    $kinds = collect($output)->pluck('type')->unique()->values()->all();
    expect($kinds)->toContain('code');
});

test('R session persists variables between runs', function () {
    if (! commandExists('Rscript')) {
        $this->markTestSkipped('Rscript not installed');
    }

    $this->actingAs($this->user)
        ->postJson("/api/projects/{$this->project->id}/r/execute", ['code' => 'persistent <- 42'])
        ->assertOk();

    $second = $this->actingAs($this->user)
        ->postJson("/api/projects/{$this->project->id}/r/execute", ['code' => 'print(persistent)'])
        ->assertOk();

    $names = collect($second->json('variables'))->pluck('name')->all();
    expect($names)->toContain('persistent');
});

function commandExists(string $cmd): bool
{
    $path = trim((string) shell_exec('command -v '.escapeshellarg($cmd).' 2>/dev/null'));

    return $path !== '';
}
