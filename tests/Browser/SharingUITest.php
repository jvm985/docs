<?php

namespace Tests\Browser;

use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class SharingUITest extends DuskTestCase
{
    /** @test */
    public function test_sharing_interface_on_production()
    {
        $this->browse(function (Browser $browser) {
            echo "\n--- STARTING SHARING UI AUDIT ---\n";

            // 1. LOGIN
            $browser->visit('https://docs.irishof.cloud/dev-login')
                    ->waitForLocation('/projects', 20);
            echo "   [OK] Logged in.\n";

            // 2. CHECK SHARE ICON
            $browser->waitForText('My Projects', 10)
                    ->assertSee('🔗');
            echo "   [OK] Share icon visible on dashboard.\n";

            // 3. OPEN SHARING MODAL
            $browser->click('button[title="Share Project"]')
                    ->waitForText('Share Project:', 10)
                    ->assertSee('Visible to everyone')
                    ->assertSee('Share with individuals');
            echo "   [OK] Sharing modal opens correctly with all options.\n";

            // 4. TEST INDIVIDUAL ADD
            $browser->clickLink('+ Add User')
                    ->waitFor('input[placeholder="User email"]', 5)
                    ->type('input[placeholder="User email"]', 'test-partner@example.com')
                    ->select('select', 'editor');
            echo "   [OK] Individual user sharing fields are functional.\n";

            echo "--- SHARING UI AUDIT SUCCESSFUL ---\n";
        });
    }
}
