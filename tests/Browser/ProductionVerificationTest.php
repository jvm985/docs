<?php

namespace Tests\Browser;

use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class ProductionVerificationTest extends DuskTestCase
{
    /** @test */
    public function test_full_system_on_production()
    {
        $this->browse(function (Browser $browser) {
            echo "\n--- STARTING PRODUCTION VERIFICATION ---\n";

            // 1. LOGIN
            echo "1. Logging in via dev-login...\n";
            $browser->visit('https://docs.irishof.cloud/dev-login')
                    ->waitForLocation('/projects', 30);
            echo "   [OK] Logged in successfully.\n";

            // 2. CREATE PROJECT
            $projectName = 'Verify ' . time();
            echo "2. Creating project: $projectName...\n";
            $browser->waitFor('@new-project-button', 10)
                    ->click('@new-project-button')
                    ->waitForText('Create New Project', 10)
                    ->type('#name', $projectName)
                    ->click('@create-project-button')
                    ->waitForText($projectName, 30);
            
            // Re-visit projects to ensure fresh state if needed, then click the link
            $browser->visit('https://docs.irishof.cloud/projects')
                    ->waitForText($projectName, 20)
                    ->clickLink($projectName);
            
            $browser->waitFor('@file-tree', 30);
            echo "   [OK] Project created and opened.\n";

            // 3. TEST LATEX
            echo "3. Testing LaTeX...\n";
            $this->createAndCompile($browser, 'test.tex', "\\documentclass{article}\n\\begin{document}\nHello LaTeX Verification\n\\end{document}", 'pdflatex');
            echo "   [OK] LaTeX compiled to PDF.\n";

            // 4. TEST TYPST
            echo "4. Testing Typst...\n";
            $this->createAndCompile($browser, 'test.typ', "= Hello Typst Verification\nTesting typst compilation.", null);
            echo "   [OK] Typst compiled to PDF.\n";

            // 5. TEST R
            echo "5. Testing R (Code & Variables)...\n";
            $browser->press('📄+')
                    ->pause(1000)
                    ->driver->switchTo()->alert()->sendKeys('script.R');
            $browser->driver->switchTo()->alert()->accept();
            $browser->waitForText('script.R', 20)->click('span[title="script.R"]')->pause(2000);

            $rCode = 'r_verify_val <- 987; print(paste("R_VERIFY_SUCCESS", r_verify_val))';
            $browser->script("
                const view = document.querySelector('[dusk=\"editor-container\"]')._cm;
                view.dispatch({changes: {from: 0, to: view.state.doc.length, insert: '$rCode'}});
            ");
            $browser->pause(3000)
                    ->click('@compile-button')
                    ->waitUntilMissing('.animate-spin', 60)
                    ->waitForText('R_VERIFY_SUCCESS 987', 30)
                    ->assertSee('R_VERIFY_SUCCESS 987');
            
            $browser->click('@tab-variables')
                    ->waitForText('r_verify_val', 20)
                    ->assertSee('987');
            echo "   [OK] R executed, output and variables verified.\n";

            echo "--- PRODUCTION VERIFICATION COMPLETE: ALL SYSTEMS GO ---\n";
        });
    }

    protected function createAndCompile(Browser $browser, $filename, $content, $compiler = null)
    {
        echo "   -> Creating $filename...\n";
        $browser->press('📄+')
                ->pause(1000)
                ->driver->switchTo()->alert()->sendKeys($filename);
        $browser->driver->switchTo()->alert()->accept();
        
        $browser->waitForText($filename, 20)
                ->click('span[title="'.$filename.'"]')
                ->pause(2000);

        $browser->script("
            const view = document.querySelector('[dusk=\"editor-container\"]')._cm;
            view.dispatch({changes: {from: 0, to: view.state.doc.length, insert: " . json_encode($content) . "}});
        ");
        
        $browser->pause(3000); // Wait for autosave

        if ($compiler) {
            $browser->select('select', $compiler);
        }

        echo "   -> Compiling $filename...\n";
        $browser->click('@compile-button')
                ->waitUntilMissing('.animate-spin', 90);
        
        $browser->waitFor('iframe', 45);
        $source = $browser->attribute('iframe', 'src');
        $this->assertStringContainsString('/storage/outputs/', $source);
    }
}
