<?php

namespace Tests\Browser;

use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class RigorousProductionTest extends DuskTestCase
{
    /** @test */
    public function test_rigorous_edge_cases_on_production()
    {
        $this->browse(function (Browser $browser) {
            echo "\n--- STARTING RIGOROUS EDGE-CASE AUDIT ---\n";

            // 1. LOGIN
            $browser->visit('https://docs.irishof.cloud/dev-login')
                    ->waitForLocation('/projects', 30);
            echo "   [OK] Logged in.\n";

            // 2. CREATE PROJECT
            $projectName = 'Audit ' . time();
            $browser->waitFor('@new-project-button', 15)
                    ->click('@new-project-button')
                    ->waitForText('Create New Project', 10)
                    ->type('#name', $projectName)
                    ->click('@create-project-button')
                    ->waitForText($projectName, 30);
            
            // Klik op het zojuist aangemaakte project via de tekst (robuuster)
            $browser->clickLink($projectName)
                    ->waitFor('@file-tree', 30);
            echo "   [OK] Project created & opened.\n";

            // 3. THE "5 geschiedenis.tex" TEST (Spaces & Numbers)
            echo "3. Testing '5 geschiedenis.tex' (Spaces & Numbers)...\n";
            $this->rigorousCompile($browser, '5 geschiedenis.tex', 
                "\\documentclass[12pt]{article}\n" .
                "\\usepackage[utf8]{inputenc}\n" .
                "\\begin{document}\n" .
                "\\section{Geschiedenis van Belgie}\n" .
                "Dit is een test met speciale tekens: e, a, i, o. \\\\ \n" .
                "Wiskunde: \$ E = mc^2 \$\n" .
                "\\end{document}", 
                'pdflatex'
            );
            echo "   [OK] '5 geschiedenis.tex' compiled perfectly.\n";

            // 4. COMPLEX TYPST TEST
            echo "4. Testing Complex Typst (Table & Layout)...\n";
            $this->rigorousCompile($browser, 'rapport v1.typ', 
                "= Rapport Uitvoer\n" .
                "#table(\n" .
                "  columns: (1fr, auto, auto),\n" .
                "  inset: 10pt,\n" .
                "  align: horizon,\n" .
                "  [*Item*], [*Status*], [*Waarde*],\n" .
                "  [CPU], [OK], [42%],\n" .
                "  [RAM], [Hoog], [88%],\n" .
                ")", 
                null
            );
            echo "   [OK] Complex Typst compiled.\n";

            // 5. R DATA PERSISTENCE & PLOT STRESS
            echo "5. Testing R Data Persistence & Plot Stress...\n";
            $browser->press('📄+')->pause(1000)->driver->switchTo()->alert()->sendKeys('analyse data.R');
            $browser->driver->switchTo()->alert()->accept();
            $browser->waitForText('analyse data.R', 20)->click('span[title="analyse data.R"]')->pause(2000);

            $rCode = "dataset_x <- rnorm(100); \n" .
                     "print('SUMMARY_START'); \n" .
                     "print(summary(dataset_x)); \n" .
                     "plot(dataset_x, main='Belangrijke Grafiek');";
            
            $browser->script("
                const view = document.querySelector('[dusk=\"editor-container\"]')._cm;
                view.dispatch({changes: {from: 0, to: view.state.doc.length, insert: " . json_encode($rCode) . "}});
            ");
            
            $browser->pause(3000)->click('@compile-button')
                    ->waitUntilMissing('.animate-spin', 60)
                    ->waitForText('SUMMARY_START', 30);
            
            $browser->waitFor('img[src*="outputs"]', 20);
            echo "   [OK] R calculation and plot verified.\n";

            echo "--- RIGOROUS AUDIT COMPLETE: ALL SYSTEMS GO ---\n";
        });
    }

    protected function rigorousCompile(Browser $browser, $filename, $content, $compiler = null)
    {
        echo "      -> Process $filename...\n";
        $browser->waitForText('📄+', 10)->press('📄+')->pause(1500)->driver->switchTo()->alert()->sendKeys($filename);
        $browser->driver->switchTo()->alert()->accept();
        $browser->waitForText($filename, 20)->click('span[title="'.$filename.'"]')->pause(2500);

        $browser->script("
            const view = document.querySelector('[dusk=\"editor-container\"]')._cm;
            view.dispatch({changes: {from: 0, to: view.state.doc.length, insert: " . json_encode($content) . "}});
        ");
        $browser->pause(3500);

        if ($compiler) $browser->select('select', $compiler);

        $browser->click('@compile-button')->waitUntilMissing('.animate-spin', 90);
        $browser->waitFor('iframe', 60);
        
        $source = $browser->attribute('iframe', 'src');
        $this->assertStringContainsString('/storage/outputs/', $source);
    }
}
