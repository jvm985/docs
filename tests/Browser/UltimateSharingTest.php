<?php

namespace Tests\Browser;

use App\Models\User;
use App\Models\Project;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class UltimateSharingTest extends DuskTestCase
{
    /** @test */
    public function test_viewer_can_compile_cross_project_include()
    {
        $this->browse(function (Browser $owner, Browser $viewer) {
            $ownerEmail = 'owner_'.time().'@test.com';
            $viewerEmail = 'viewer_'.time().'@test.com';

            // 1. SETUP OWNER & PROJECTS via live server
            echo "\n--- STARTING LIVE INTEGRATION TEST ---\n";
            echo "1. Creating Users in live database...\n";
            $ownerUser = User::factory()->create(['email' => $ownerEmail]);
            $viewerUser = User::factory()->create(['email' => $viewerEmail]);

            echo "2. Owner creating Project 'bbb'...\n";
            $owner->loginAs($ownerUser)
                  ->visit('https://docs.irishof.cloud/projects')
                  ->waitFor('@new-project-button', 20)
                  ->click('@new-project-button')
                  ->waitForText('Create New Project', 10)
                  ->type('#name', 'bbb')
                  ->click('@create-project-button')
                  ->waitForText('bbb', 30);
            
            $projectBBB = Project::where('name', 'bbb')->where('user_id', $ownerUser->id)->first();
            $owner->visit("https://docs.irishof.cloud/projects/{$projectBBB->id}")->waitFor('@file-tree', 20);
            $this->createFileViaJS($owner, 'napoleon.tex', 'Napoleon was hier.');

            echo "3. Owner creating Project 'geschiedenis'...\n";
            $owner->visit('https://docs.irishof.cloud/projects')
                  ->waitFor('@new-project-button', 20)
                  ->click('@new-project-button')
                  ->waitForText('Create New Project', 10)
                  ->type('#name', 'geschiedenis')
                  ->click('@create-project-button')
                  ->waitForText('geschiedenis', 30);
            
            $projectGesh = Project::where('name', 'geschiedenis')->where('user_id', $ownerUser->id)->first();
            $owner->visit("https://docs.irishof.cloud/projects/{$projectGesh->id}")->waitFor('@file-tree', 20);
            $this->createFileViaJS($owner, '5_geschiedenis.tex', "\\documentclass{article}\n\\begin{document}\nHoofdtest\n\\include{../bbb/napoleon.tex}\n\\end{document}");

            echo "4. Sharing 'geschiedenis' with viewer...\n";
            $owner->visit('https://docs.irishof.cloud/projects')
                  ->waitForText('My Projects', 20)
                  ->click('button[title="Share Project"]') // Klik op de eerste (geschiedenis)
                  ->waitForText('Share Project:', 10)
                  ->clickLink('+ Add User')
                  ->waitFor('input[placeholder="User email"]', 10)
                  ->type('input[placeholder="User email"]', $viewerUser->email)
                  ->select('select', 'viewer')
                  ->press('Save Changes')
                  ->pause(2000);

            echo "5. Viewer attempting cross-project compile...\n";
            $viewer->loginAs($viewerUser)
                   ->visit("https://docs.irishof.cloud/projects/{$projectGesh->id}")
                   ->waitFor('@file-tree', 30)
                   ->click('span[title="5_geschiedenis.tex"]')
                   ->pause(2000)
                   ->click('@compile-button');

            echo "6. Waiting for PDF generation (max 90s)...\n";
            $viewer->waitUntilMissing('.animate-spin', 90)
                   ->waitFor('iframe', 60);

            $source = $viewer->attribute('iframe', 'src');
            echo "   [SUCCESS] PDF Generated for viewer: $source\n";
            
            $this->assertStringContainsString('/storage/outputs/', $source);
            echo "--- LIVE INTEGRATION TEST PASSED ---\n";
        });
    }

    protected function createFileViaJS(Browser $browser, $name, $content)
    {
        $browser->waitForText('📄+', 10)->press('📄+')->pause(1000)->driver->switchTo()->alert()->sendKeys($name);
        $browser->driver->switchTo()->alert()->accept();
        $browser->waitForText($name, 15)->click('span[title="'.$name.'"]')->pause(2000);
        $browser->script("
            const view = document.querySelector('[dusk=\"editor-container\"]')._cm;
            view.dispatch({changes: {from: 0, to: view.state.doc.length, insert: ".json_encode($content)."}});
        ");
        $browser->pause(3000); // Wait for autosave
    }
}
