<?php

use App\Models\Project;
use App\Models\User;
use App\Models\File;
use App\Actions\CompileFileAction;

test('ULTIMATE USER EXPERIENCE TEST', function () {
    $user = User::where('email', 'joachim.vanmeirvenne@atheneumkapellen.be')->first();
    if (!$user) $this->fail("User Joachim not found in DB");
    
    auth()->login($user);
    $action = new CompileFileAction();

    echo "\n🚀 STARTING ULTIMATE TEST...\n";

    // 1. TEST TYPST
    $p_typst = Project::where('name', 'LIKE', '%typst%')->first();
    if ($p_typst) {
        echo "📄 Testing Typst Compilation (" . $p_typst->name . ")...";
        $f_typst = $p_typst->files()->where('name', 'main.typ')->first();
        if ($f_typst) {
            $start = microtime(true);
            $res_typst = $action->execute($f_typst);
            echo " DONE (" . round(microtime(true) - $start, 2) . "s)\n";
            expect($res_typst['type'])->toBe('pdf');
        } else {
            echo " SKIPPED (No main.typ)\n";
        }
    }

    // 2. TEST LATEX
    $p_latex = Project::find(244) ?: Project::where('name', '05_geschiedenis')->first();
    if ($p_latex) {
        echo "📄 Testing LaTeX Compilation (" . $p_latex->name . ")...";
        $f_latex = $p_latex->files()->where('name', '5_geschiedenis.tex')->first();
        if ($f_latex) {
            $start = microtime(true);
            $res_latex = $action->execute($f_latex);
            echo " DONE (" . round(microtime(true) - $start, 2) . "s)\n";
            expect($res_latex['type'])->toBe('pdf');
        } else {
            echo " SKIPPED (No main tex file)\n";
        }
    }

    // 3. TEST R WITH PLOTS
    echo "📊 Testing R with Plots...";
    $p_r = $user->projects()->first();
    if ($p_r) {
        $f_r = $p_r->files()->create([
            'name' => 'test_plots_robot.r',
            'type' => 'file',
            'extension' => 'r',
            'content' => "print('Hello Robot'); x <- 1:20; y <- cumsum(rnorm(20)); plot(x,y, type='l'); print(mean(y));"
        ]);
        
        try {
            $start = microtime(true);
            $res_r = $action->execute($f_r);
            echo " DONE (" . round(microtime(true) - $start, 2) . "s)\n";
            
            expect($res_r['type'])->toBe('r');
            expect($res_r['result']['plots'])->not->toBeEmpty();
            expect($res_r['result']['variables'])->not->toBeEmpty();
        } finally {
            $f_r->delete();
        }
    } else {
        echo " SKIPPED (No project for R)\n";
    }

    echo "✅ ALL SYSTEMS OPERATIONAL!\n";
});
