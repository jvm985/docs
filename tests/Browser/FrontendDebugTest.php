<?php

namespace Tests\Browser;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class FrontendDebugTest extends DuskTestCase
{
    use DatabaseMigrations;

    public function test_output_panel_renders_correctly()
    {
        $user = User::factory()->create();

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                    ->visit("/debug-frontend")
                    ->waitForText('DEBUG_TEST', 10)
                    ->assertSee('DEBUG_TEST')
                    ->assertSee('Variables')
                    ->clickText('Variables')
                    ->waitForText('42', 5)
                    ->assertSee('42');
        });
    }
}
