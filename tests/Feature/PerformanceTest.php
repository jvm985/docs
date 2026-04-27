<?php

use App\Models\Project;
use App\Models\User;
use App\Models\File;
use App\Actions\CompileFileAction;
use App\Services\WorkspaceManager;

test('ROBOT DEEP AUDIT: LaTeX and R (Filesystem Edition)', function () {
    $user = User::where('email', 'joachim.vanmeirvenne@atheneumkapellen.be')->first();
    if (!$user) $this->fail("User Joachim not found. Please seed the DB.");
    
    auth()->login($user);
    $manager = new WorkspaceManager();
    $action = new CompileFileAction($manager);

    echo "\n🤖 ROBOT STARTING ARCHITECTURE AUDIT V138...\n";

    // 1. TEST LATEX
    $p_latex = $user->projects()->where('name', 'Demo Project')->first();
    if ($p_latex) {
        echo "📄 Testing LaTeX (Demo Project)...";
        $f_latex = $p_latex->files()->where('name', 'main.tex')->first();
        $start = microtime(true);
        $res = $action->execute($f_latex);
        echo " DONE (" . round(microtime(true) - $start, 2) . "s)\n";
        expect($res['type'])->toBe('pdf');
    }

    // 2. TEST R WITH PLOTS
    echo "📊 Testing R with Plots...";
    $f_r = $p_latex->files()->where('name', 'analysis.r')->first();
    $start = microtime(true);
    $res = $action->execute($f_r);
    echo " DONE (" . round(microtime(true) - $start, 2) . "s)\n";
    
    expect($res['type'])->toBe('r');
    expect($res['result']['plots'])->not->toBeEmpty();

    echo "✅ ARCHITECTURE AUDIT COMPLETE!\n";
});
