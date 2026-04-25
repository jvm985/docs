<?php

use Illuminate\Support\Facades\Http;

test('the live site is reachable and serves the correct version', function () {
    $response = Http::get('https://docs.irishof.cloud/login');
    
    expect($response->status())->toBe(200);
    
    // Check of de HTML de Inertia app container heeft
    expect($response->body())->toContain('id="app"');
    
    // Check of de Vite manifest correct wordt geladen
    expect($response->body())->toContain('/build/assets/app-');
});

test('the javascript bundle contains the inertia initialization', function () {
    // Haal de HTML op om de JS URL te vinden
    $html = Http::get('https://docs.irishof.cloud/login')->body();
    
    preg_match('/\/build\/assets\/app-[a-zA-Z0-9_-]+\.js/', $html, $matches);
    $jsUrl = 'https://docs.irishof.cloud' . ($matches[0] ?? '');
    
    if ($jsUrl === 'https://docs.irishof.cloud') {
        $this->fail("Geen Javascript bundle gevonden in HTML");
    }
    
    $jsContent = Http::get($jsUrl)->body();
    
    // Check of onze unieke marker aanwezig is in de bundle
    expect($jsContent)->toContain('PEST-V100-REAL');
});
