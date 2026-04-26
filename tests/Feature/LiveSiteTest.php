<?php

use Illuminate\Support\Facades\Http;

test('the live site serves the V112 version with no-cache headers', function () {
    $response = Http::get('https://docs.irishof.cloud/login');
    
    expect($response->status())->toBe(200);
    
    // Check de NO-CACHE headers
    expect($response->header('Cache-Control'))->toContain('no-cache');
    expect($response->header('Pragma'))->toBe('no-cache');
    
    // Check de HTML op de build_v112 directory
    expect($response->body())->toContain('build_v112');
    
    // Haal de JS bundle op om de V112 marker te vinden
    preg_match('/\/build_v112\/assets\/app-[a-zA-Z0-9_-]+\.js/', $response->body(), $matches);
    $jsUrl = 'https://docs.irishof.cloud' . ($matches[0] ?? '');
    
    if ($jsUrl === 'https://docs.irishof.cloud') {
        $this->fail("Geen build_v112 Javascript bundle gevonden in HTML");
    }
    
    $jsContent = Http::get($jsUrl)->body();
    expect($jsContent)->toContain('VERSION-V112-CLEAR');
});
