<?php

use App\Models\Project;
use App\Models\User;
use App\Models\File;
use App\Actions\CompileFileAction;

test('ROBOT DEEP AUDIT: LaTeX, Typst and R', function () {
    $user = User::where('email', 'joachim.vanmeirvenne@atheneumkapellen.be')->first();
    if (!$user) $this->fail("User Joachim not found in DB");
    
    auth()->login($user);
    $action = new CompileFileAction();

    echo "\n🤖 ROBOT STARTING DEEP AUDIT V134...\n";

    // 1. TEST TYPST
    $p_typst = Project::where('name', 'LIKE', '%typst%')->first();
    if ($p_typst) {
        echo "📄 Testing Typst (" . $p_typst->name . ")...";
        $f_typst = $p_typst->files()->where('name', 'main.typ')->first() 
                 ?: $p_typst->files()->where('extension', 'typ')->first();
        
        if ($f_typst) {
            $start = microtime(true);
            $res = $action->execute($f_typst);
            echo " DONE (" . round(microtime(true) - $start, 2) . "s)\n";
            expect($res['type'])->toBe('pdf');
        } else {
            echo " SKIPPED (No typst files found)\n";
        }
    } else {
        echo " SKIPPED (No typst project found)\n";
    }

    // 2. TEST LATEX
    $p_latex = Project::find(244) ?: Project::where('name', 'LIKE', '%geschiedenis%')->where('name', 'NOT LIKE', '%typst%')->first();
    if ($p_latex) {
        echo "📄 Testing LaTeX (" . $p_latex->name . ")...";
        $f_latex = $p_latex->files()->where('name', '5_geschiedenis.tex')->first()
                 ?: $p_latex->files()->where('extension', 'tex')->first();
        
        if ($f_latex) {
            $start = microtime(true);
            $res = $action->execute($f_latex);
            echo " DONE (" . round(microtime(true) - $start, 2) . "s)\n";
            expect($res['type'])->toBe('pdf');
        } else {
            echo " SKIPPED (No tex files found)\n";
        }
    }

    // 3. TEST R WITH PLOTS
    echo "📊 Testing R with Plots...";
    $p_r = $user->projects()->first();
    if ($p_r) {
        $f_r = $p_r->files()->create([
            'name' => 'robot_audit_r_v134.r',
            'type' => 'file',
            'extension' => 'r',
            'content' => "x <- 1:20; y <- cumsum(rnorm(20)); plot(x, y, type='l'); print('R Operational');"
        ]);
        
        try {
            $start = microtime(true);
            $res = $action->execute($f_r);
            echo " DONE (" . round(microtime(true) - $start, 2) . "s)\n";
            expect($res['result']['plots'])->not->toBeEmpty();
        } finally {
            $f_r->delete();
        }
    }

    echo "✅ AUDIT COMPLETE - ALL SYSTEMS NOMINAL!\n";
});
