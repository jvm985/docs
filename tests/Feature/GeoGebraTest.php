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

test('readFile returns interactive kind for .ggb files', function () {
    // Een minimaal .ggb-bestand is een zip; voor de read-path volstaat dat de
    // bytes op schijf staan en de extensie .ggb is.
    $this->files->create($this->project, 'tekening.ggb', 'file', "PK\x03\x04dummy-ggb-bytes");

    $data = $this->files->readFile($this->project, 'tekening.ggb');

    expect($data['kind'])->toBe('interactive');
    expect($data['subkind'])->toBe('geogebra');
    expect($data['extension'])->toBe('ggb');
    expect($data)->toHaveKey('v');
});

test('GET /file augments interactive payload with asset url', function () {
    $this->files->create($this->project, 'fig.ggb', 'file', "PK\x03\x04binary");

    $resp = $this->actingAs($this->user)
        ->getJson("/api/projects/{$this->project->id}/file?path=fig.ggb")
        ->assertOk();

    expect($resp->json('kind'))->toBe('interactive');
    expect($resp->json('subkind'))->toBe('geogebra');
    expect($resp->json('url'))->toContain('/editor/'.$this->project->id.'/asset');
});

test('PUT /file/binary writes base64-decoded bytes back to disk', function () {
    $this->files->create($this->project, 'fig.ggb', 'file', "PK\x03\x04initial");

    $newBytes = "PK\x03\x04UPDATED\x00\x01\x02";
    $resp = $this->actingAs($this->user)
        ->putJson("/api/projects/{$this->project->id}/file/binary", [
            'path' => 'fig.ggb',
            'base64' => base64_encode($newBytes),
        ])
        ->assertOk();

    expect($resp->json('ok'))->toBeTrue();

    $abs = $this->files->absolutePath($this->project, 'fig.ggb');
    expect(file_get_contents($abs))->toBe($newBytes);
});

test('PUT /file/binary rejects invalid base64', function () {
    $this->files->create($this->project, 'fig.ggb', 'file', 'old');

    $this->actingAs($this->user)
        ->putJson("/api/projects/{$this->project->id}/file/binary", [
            'path' => 'fig.ggb',
            'base64' => 'not===valid===base64!@#',
        ])
        ->assertStatus(500);
});

test('PUT /file/binary requires write permission', function () {
    $other = User::factory()->create();
    $this->files->create($this->project, 'fig.ggb', 'file', 'bytes');

    $this->actingAs($other)
        ->putJson("/api/projects/{$this->project->id}/file/binary", [
            'path' => 'fig.ggb',
            'base64' => base64_encode('hack'),
        ])
        ->assertStatus(403);
});
