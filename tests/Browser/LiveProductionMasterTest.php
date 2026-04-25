<?php

namespace Tests\Browser;

use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class LiveProductionMasterTest extends DuskTestCase
{
    /** @test */
    public function test_full_workflow_on_production()
    {
        $this->browse(function (Browser $browser) {
            echo "\n--- STARTING MASTER PRODUCTION AUDIT ---\n";

            // 1. LOGIN via dev-login (simuleert Google succes)
            echo "1. Attempting login...\n";
            $browser->visit('https://docs.irishof.cloud/dev-login')
                    ->waitForLocation('/dashboard', 15)
                    ->assertPathIs('/projects');
            echo "   [OK] Session persisted. Redirected to Projects.\n";

            // 2. PROJECT AANMAKEN
            echo "2. Creating project...\n";
            $projectName = 'Dusk Test ' . time();
            $browser->click('@new-project-button')
                    ->waitForText('Create New Project')
                    ->type('#name', $projectName)
                    ->click('@create-project-button')
                    ->waitForText($projectName, 15);
            echo "   [OK] Project created and visible.\n";

            // 3. PROJECT OPENEN & R TEST
            echo "3. Testing R compilation...\n";
            $browser->clickLink($projectName)
                    ->waitFor('@file-tree', 10)
                    // Nieuw R bestand
                    ->press('📄+')
                    ->pause(500)
                    ->driver->switchTo()->alert()->sendKeys('test.R');
            $browser->driver->switchTo()->alert()->accept();
            
            $browser->waitForText('test.R', 10)
                    ->click('span[title="test.R"]')
                    ->pause(1000);

            // Code injecteren via CodeMirror
            $content = 'x <- 42; print(paste("DUSK_OK", x))';
            $browser->script("
                const container = document.querySelector('[dusk=\"editor-container\"]');
                if (container && container._cm) {
                    const view = container._cm;
                    const transaction = view.state.update({changes: {from: 0, to: view.state.doc.length, insert: '$content'}});
                    view.dispatch(transaction);
                }
            ");
            $browser->pause(2000); // Wacht op autosave

            $browser->click('@compile-button')
                    ->waitUntilMissing('.animate-spin', 40)
                    ->waitForText('DUSK_OK 42', 20)
                    ->assertSee('DUSK_OK 42');
            echo "   [OK] R code compiled and output received on production.\n";

            // 4. VARIABELEN CHECK
            echo "4. Checking variables...\n";
            $browser->click('@tab-variables')
                    ->waitForText('x', 10)
                    ->assertSee('x')
                    ->assertSee('42');
            echo "   [OK] Variables captured correctly.\n";

            echo "--- MASTER AUDIT SUCCESSFUL ---\n";
        });
    }
}
