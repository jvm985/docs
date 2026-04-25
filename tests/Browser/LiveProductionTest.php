<?php

namespace Tests\Browser;

use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class LiveProductionTest extends DuskTestCase
{
    /** @test */
    public function test_google_login_click_on_production()
    {
        $this->browse(function (Browser $browser) {
            echo "\n--- STARTING LIVE CLICK TEST ---\n";
            
            $browser->visit('https://docs.irishof.cloud/login')
                    ->waitForText('Login with Google', 10);
            
            echo "1. Button found. Clicking now...\n";
            
            // We klikken op de knop
            $browser->clickLink('Login with Google');
            
            echo "2. Waiting for Google redirect...\n";
            
            // We wachten tot de URL verandert naar accounts.google.com
            $browser->pause(5000); 
            
            $url = $browser->driver->getCurrentURL();
            echo "Final URL after click: $url\n";
            
            if (str_contains($url, 'accounts.google.com')) {
                echo "SUCCESS: App redirected to Google successfully.\n";
            } else {
                echo "FAILURE: Still on " . $url . "\n";
            }
            
            $this->assertStringContainsString('accounts.google.com', $url);
            
            echo "--- TEST COMPLETE ---\n";
        });
    }
}
