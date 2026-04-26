<?php

use App\Models\Project;
use App\Models\User;
use App\Models\File;
use App\Actions\CompileFileAction;

test('ROBOT DEEP AUDIT: LaTeX, Typst and R', function () {
    $user = User::where('email', 'joachim.vanmeirvenne@atheneumkapellen.be')->first();
    auth()->login($user);
    $action = new CompileFileAction();

    echo "\n🤖 ROBOT STARTING DEEP AUDIT V125...\n";

    // 1. TEST TYPST (Project 269)
    echo "📄 Testing Typst (05 geschiedenis typst v12)...";
    $f_typst = File::where('project_id', 269)->where('name', 'main.typ')->first();
    if ($f_typst) {
        $start = microtime(true);
        $res = $action->execute($f_typst);
        echo " DONE (" . round(microtime(true) - $start, 2) . "s)\n";
        expect($res['type'])->toBe('pdf');
    } else {
        $this->fail("main.typ not found in project 269");
    }

    // 2. TEST LATEX (Project 244)
    echo "📄 Testing LaTeX (05_geschiedenis)...";
    $f_latex = File::where('project_id', 244)->where('name', '5_geschiedenis.tex')->first();
    $start = microtime(true);
    $res = $action->execute($f_latex);
    echo " DONE (" . round(microtime(true) - $start, 2) . "s)\n";
    expect($res['type'])->toBe('pdf');

    // 3. TEST R WITH PLOTS
    echo "📊 Testing R with Plots...";
    $p_r = $user->projects()->first();
    $f_r = $p_r->files()->create([
        'name' => 'robot_audit_r.r',
        'type' => 'file',
        'extension' => 'r',
        'content' => "x <- 1:10; plot(x, x^2); print('R Operational');"
    ]);
    
    try {
        $start = microtime(true);
        $res = $action->execute($f_r);
        echo " DONE (" . round(microtime(true) - $start, 2) . "s)\n";
        expect($res['result']['plots'])->not->toBeEmpty();
    } finally {
        $f_r->delete();
    }

    echo "✅ AUDIT COMPLETE - ALL SYSTEMS NOMINAL!\n";
});
