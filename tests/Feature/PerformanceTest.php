<?php

use App\Models\Project;
use App\Models\User;
use App\Models\File;
use App\Actions\CompileFileAction;

test('ULTIMATE USER EXPERIENCE TEST', function () {
    $user = User::where('email', 'joachim.vanmeirvenne@atheneumkapellen.be')->first();
    auth()->login($user);
    $action = new CompileFileAction();

    echo "\n🚀 STARTING ULTIMATE TEST...\n";

    // 1. TEST TYPST (Project 265/266)
    echo "📄 Testing Typst Compilation (Project 269)...";
    $p_typst = Project::where('name', 'LIKE', '%typst%')->first();
    $f_typst = $p_typst->files()->where('name', 'main.typ')->first();
    $start = microtime(true);
    $res_typst = $action->execute($f_typst);
    echo " DONE (" . round(microtime(true) - $start, 2) . "s)\n";
    expect($res_typst['type'])->toBe('pdf');
    expect($res_typst['url'])->not->toBeNull();

    // 2. TEST LATEX (Project 244)
    echo "📄 Testing LaTeX Compilation (Project 244)...";
    $p_latex = Project::find(244);
    $f_latex = $p_latex->files()->where('name', '5_geschiedenis.tex')->first();
    $start = microtime(true);
    $res_latex = $action->execute($f_latex);
    echo " DONE (" . round(microtime(true) - $start, 2) . "s)\n";
    expect($res_latex['type'])->toBe('pdf');

    // 3. TEST R WITH PLOTS
    echo "📊 Testing R with Plots...";
    // Maak een tijdelijke R file in Joachim's eerste project
    $p_r = $user->projects()->first();
    $f_r = $p_r->files()->create([
        'name' => 'test_plots.r',
        'type' => 'file',
        'extension' => 'r',
        'content' => "print('Hello R'); x <- 1:10; y <- x^2; plot(x,y); print(summary(y));"
    ]);
    
    $start = microtime(true);
    $res_r = $action->execute($f_r);
    echo " DONE (" . round(microtime(true) - $start, 2) . "s)\n";
    
    expect($res_r['type'])->toBe('r');
    expect($res_r['result']['plots'])->not->toBeEmpty();
    expect($res_r['result']['variables'])->not->toBeEmpty();
    
    $f_r->delete();

    echo "✅ ALL SYSTEMS OPERATIONAL!\n";
});
