<?php

use App\Models\Project;
use App\Models\User;
use App\Actions\CompileFileAction;

test('persistent workspace compilation is fast and produces a pdf', function () {
    $user = User::where('email', 'joachim.vanmeirvenne@atheneumkapellen.be')->first();
    auth()->login($user);

    $project = Project::where('name', '05 geschiedenis typst')->first();
    $file = $project->files()->where('name', 'main.typ')->first();

    $action = new CompileFileAction();
    
    // Eerste run (mag iets langer duren door initiële sync)
    $start = microtime(true);
    $result1 = $action->execute($file);
    $time1 = microtime(true) - $start;
    
    expect($result1['result'])->toBeTrue();
    expect($result1['url'])->not->toBeNull();
    
    // Tweede run (moet razendsnel zijn door incrementele sync)
    $start = microtime(true);
    $result2 = $action->execute($file);
    $time2 = microtime(true) - $start;

    expect($result2['result'])->toBeTrue();
    
    // Log de tijden voor de robot
    echo "\n⏱️ First run: " . round($time1, 2) . "s\n";
    echo "⏱️ Second run: " . round($time2, 2) . "s\n";
    
    // De tweede run moet sneller zijn dan de eerste, of in ieder geval onder de 5 seconden voor 114 pagina's
    expect($time2)->toBeLessThan(5);
});
