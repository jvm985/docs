<?php

use Illuminate\Support\Facades\Http;

test('the live site serves the proper version with no-cache headers', function () {
    $response = Http::get('https://docs.irishof.cloud/login');
    
    expect($response->status())->toBe(200);
    
    // Check de NO-CACHE headers
    expect($response->header('Cache-Control'))->toContain('no-cache');
    
    // De HTML moet nu naar de standaard 'build' directory wijzen
    expect($response->body())->toContain('/build/assets/app-');
    
    // De oude V112 marker moet weg zijn (code is opgeschoond)
    expect($response->body())->not->toContain('VERSION-V112-CLEAR');
});
