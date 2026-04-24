<?php

namespace Tests\Browser;

use App\Models\User;
use App\Models\Project;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class RCompilationTest extends DuskTestCase
{
    use DatabaseMigrations;

    public function test_project_index_loads()
    {
        $user = User::factory()->create();
        Project::create(['user_id' => $user->id, 'name' => 'Browser Test Project']);

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                    ->visit("/projects")
                    ->waitForText('Browser Test Project', 20)
                    ->assertSee('Browser Test Project');
        });
    }
}
