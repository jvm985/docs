<?php

namespace Tests\Feature;

use App\Models\File;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocsAppTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Project $project;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create();
        $this->project = Project::create([
            'user_id' => $this->user->id,
            'name' => 'Test Project',
            'description' => 'A project for testing'
        ]);
        
        Storage::fake('public');
    }

    public function test_a_user_can_view_their_projects()
    {
        $response = $this->actingAs($this->user)
            ->get(route('projects.index'));

        $response->assertStatus(200)
            ->assertInertia(fn ($page) => $page
                ->component('Projects/Index')
                ->has('projects', 1)
            );
    }

    public function test_a_user_can_create_a_project()
    {
        $response = $this->actingAs($this->user)
            ->post(route('projects.store'), [
                'name' => 'New Project',
                'description' => 'Testing project creation'
            ]);

        $this->assertDatabaseHas('projects', ['name' => 'New Project']);
        $response->assertRedirect();
    }

    public function test_a_user_can_add_a_file_to_a_project()
    {
        $response = $this->actingAs($this->user)
            ->post(route('files.store'), [
                'project_id' => $this->project->id,
                'name' => 'test.tex',
                'type' => 'file'
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('files', [
            'project_id' => $this->project->id,
            'name' => 'test.tex',
            'extension' => 'tex'
        ]);
    }

    public function test_a_user_can_upload_a_file()
    {
        $file = UploadedFile::fake()->create('document.pdf', 100);

        $response = $this->actingAs($this->user)
            ->post(route('files.upload'), [
                'project_id' => $this->project->id,
                'file' => $file
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('files', [
            'name' => 'document.pdf',
            'extension' => 'pdf'
        ]);
    }

    public function test_a_user_can_update_file_content_autosave()
    {
        $file = File::create([
            'project_id' => $this->project->id,
            'name' => 'test.txt',
            'type' => 'file',
            'extension' => 'txt',
            'content' => 'Old content'
        ]);

        $response = $this->actingAs($this->user)
            ->patch(route('files.update', $file->id), [
                'content' => 'New updated content'
            ]);

        $response->assertStatus(200);
        $this->assertEquals('New updated content', $file->fresh()->content);
    }

    public function test_a_user_can_delete_a_file()
    {
        $file = File::create([
            'project_id' => $this->project->id,
            'name' => 'delete_me.txt',
            'type' => 'file'
        ]);

        $response = $this->actingAs($this->user)
            ->delete(route('files.destroy', $file->id));

        $response->assertStatus(200);
        $this->assertDatabaseMissing('files', ['id' => $file->id]);
    }

    public function test_a_user_can_rename_a_file()
    {
        $file = File::create([
            'project_id' => $this->project->id,
            'name' => 'old_name.txt',
            'type' => 'file'
        ]);

        $response = $this->actingAs($this->user)
            ->patch(route('files.update', $file->id), [
                'name' => 'new_name.txt'
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('files', [
            'id' => $file->id,
            'name' => 'new_name.txt'
        ]);
    }

    public function test_a_user_can_duplicate_a_file()
    {
        $file = File::create([
            'project_id' => $this->project->id,
            'name' => 'original.txt',
            'type' => 'file',
            'content' => 'Sample content'
        ]);

        $response = $this->actingAs($this->user)
            ->post(route('files.duplicate', $file->id));

        $response->assertStatus(200);
        $this->assertDatabaseHas('files', [
            'name' => 'Copy of original.txt',
            'content' => 'Sample content'
        ]);
    }

    public function test_a_user_can_compile_a_latex_file()
    {
        $file = File::create([
            'project_id' => $this->project->id,
            'name' => 'test.tex',
            'type' => 'file',
            'extension' => 'tex',
            'content' => "\\documentclass{article}\\begin{document}Test\\end{document}"
        ]);

        $response = $this->actingAs($this->user)
            ->post(route('files.compile', $file->id), [
                'compiler' => 'pdflatex'
            ]);

        $response->assertStatus(200);
        $response->assertJson([
            'type' => 'pdf'
        ]);
        $this->assertNotNull($response->json('url'));
        
        // Check if file exists in fake storage
        $path = str_replace('/storage/', '', $response->json('url'));
        Storage::disk('public')->assertExists($path);
    }

    public function test_a_user_cannot_access_another_users_project()
    {
        $otherUser = User::factory()->create();
        
        $response = $this->actingAs($otherUser)
            ->get(route('projects.show', $this->project->id));

        $response->assertStatus(403);
    }
}
