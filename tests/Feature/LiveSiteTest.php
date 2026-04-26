<?php

use Illuminate\Support\Facades\Http;

test('the live site serves the V105 hard reset version', function () {
    $response = Http::get('https://docs.irishof.cloud/login');
    
    expect($response->status())->toBe(200);
    
    // Check de HTML op de build_v105 directory
    expect($response->body())->toContain('build_v105');
    
    // Haal de JS bundle op om de marker te vinden
    preg_match('/\/build_v105\/assets\/app-[a-zA-Z0-9_-]+\.js/', $response->body(), $matches);
    $jsUrl = 'https://docs.irishof.cloud' . ($matches[0] ?? '');
    
    if ($jsUrl === 'https://docs.irishof.cloud') {
        $this->fail("Geen build_v105 Javascript bundle gevonden");
    }
    
    $jsContent = Http::get($jsUrl)->body();
    expect($jsContent)->toContain('VERSION-V105-HARD-RESET');
});
