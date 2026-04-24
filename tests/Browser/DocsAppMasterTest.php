<?php

namespace Tests\Browser;

use App\Models\User;
use App\Models\Project;
use App\Models\File;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class DocsAppMasterTest extends DuskTestCase
{
    use DatabaseMigrations;

    /** @test */
    public function test_full_system_audit()
    {
        $user = User::factory()->create(['email' => 'master@test.com']);

        $this->browse(function (Browser $browser) use ($user) {
            // 1. LOGIN & PROJECT
            $browser->loginAs($user)
                    ->visit('/projects')
                    ->waitForText('Projects', 10)
                    ->click('@new-project-button')
                    ->waitForText('Create New Project')
                    ->type('#name', 'Master Audit Project')
                    ->click('@create-project-button')
                    ->waitForText('Master Audit Project', 15);

            $project = Project::where('name', 'Master Audit Project')->first();
            $browser->visit("/projects/{$project->id}")->waitFor('@file-tree');

            // 2. TEST R EXECUTION & VARIABLES
            $browser->press('📄+')
                    ->pause(500)
                    ->driver->switchTo()->alert()->sendKeys('script.R');
            $browser->driver->switchTo()->alert()->accept();
            
            $browser->waitForText('script.R', 10)
                    ->click('span[title="script.R"]')
                    ->pause(1000);

            $content = 'my_test_var <- 99; print(paste("R_OK", my_test_var))';
            $browser->script("
                const container = document.querySelector('[dusk=\"editor-container\"]');
                const view = container._cm;
                const transaction = view.state.update({changes: {from: 0, to: view.state.doc.length, insert: '$content'}});
                view.dispatch(transaction);
            ");
            $browser->pause(2000);

            $browser->click('@compile-button')
                    ->waitUntilMissing('.animate-spin', 40)
                    ->waitForText('R_OK 99', 20)
                    ->assertSee('R_OK 99')
                    ->click('@tab-variables')
                    ->waitForText('my_test_var', 10)
                    ->assertSee('my_test_var')
                    ->assertSee('99');

            // 3. TEST MARKDOWN
            $this->createAndTestFile($browser, 'test.md', '# MD_OK', 'MD_OK', true);

            // 4. TEST RMARKDOWN
            $this->createAndTestFile($browser, 'report.Rmd', '---\ntitle: "RMD"\n---\n# RMD_OK\n```{r}\nprint("RMD_CODE_OK")\n```', 'RMD_CODE_OK', true);

            // 5. TEST LATEX
            $this->createAndTestFile($browser, 'doc.tex', '\\\\documentclass{article}\\\\begin{document}LATEX_OK\\\\end{document}', 'LATEX_OK', true);
        });
    }

    protected function createAndTestFile(Browser $browser, $name, $content, $expectedText, $isPdf = false)
    {
        $browser->press('📄+')
                ->pause(500)
                ->driver->switchTo()->alert()->sendKeys($name);
        $browser->driver->switchTo()->alert()->accept();
        
        $browser->waitForText($name, 10)
                ->click('span[title="'.$name.'"]')
                ->pause(1000);

        $browser->script("
            const container = document.querySelector('[dusk=\"editor-container\"]');
            const view = container._cm;
            const transaction = view.state.update({changes: {from: 0, to: view.state.doc.length, insert: '$content'}});
            view.dispatch(transaction);
        ");
        $browser->pause(2000);

        $browser->click('@compile-button')
                ->waitUntilMissing('.animate-spin', 40);

        if ($isPdf) {
            $browser->waitFor('iframe', 25);
            $source = $browser->attribute('iframe', 'src');
            $this->assertStringContainsString('/storage/outputs/', $source);
        } else {
            $browser->waitForText($expectedText, 20)
                    ->assertSee($expectedText);
        }
    }
}
