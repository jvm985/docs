<?php

namespace App\Console\Commands;

use App\Models\File;
use App\Models\Project;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Http\Controllers\FileController;

class AuditCompilers extends Command
{
    protected $signature = 'app:audit-compilers';
    protected $description = 'Deep audit of all document compilers and R execution logic';

    public function handle()
    {
        $this->header("Starting Deep Audit of Compilers");

        $user = User::first() ?: User::factory()->create(['email' => 'audit@example.com']);
        auth()->login($user);

        $project = Project::firstOrCreate(
            ['user_id' => $user->id, 'name' => 'Audit Project'],
            ['description' => 'Automatic audit project']
        );

        $tests = [
            'LaTeX' => [
                'name' => 'test_audit.tex',
                'content' => "\\documentclass{article}\\begin{document}Audit LaTeX OK\\end{document}",
                'ext' => 'tex'
            ],
            'Typst' => [
                'name' => 'test_audit.typ',
                'content' => "= Audit Typst OK",
                'ext' => 'typ'
            ],
            'Markdown' => [
                'name' => 'test_audit.md',
                'content' => "# Audit Markdown OK",
                'ext' => 'md'
            ],
            'R' => [
                'name' => 'test_audit.R',
                'content' => "audit_val <- 42\nprint(paste('Audit R OK', audit_val))\nplot(1:10)",
                'ext' => 'R'
            ]
        ];

        foreach ($tests as $type => $data) {
            $this->testCompiler($project, $type, $data);
        }

        $this->info("\nAudit Complete.");
    }

    protected function testCompiler($project, $label, $data)
    {
        $this->comment("\nTesting $label...");

        $file = File::updateOrCreate(
            ['project_id' => $project->id, 'name' => $data['name']],
            ['type' => 'file', 'extension' => $data['ext'], 'content' => $data['content']]
        );

        $action = new \App\Actions\CompileFileAction();
        $response = $action->execute($file, $data['options'] ?? []);
        $content = (object)$response;

        if ($content->type === 'pdf') {
            $this->info("  [OK] Generated PDF: " . $content->url);
        } elseif ($content->type === 'r') {
            $this->info("  [OK] R Execution Successful");
            if (isset($content->result['structured_output']) && count($content->result['structured_output']) > 0) {
                $this->info("  [OK] Structured output received (" . count($content->result['structured_output']) . " lines)");
                foreach ($content->result['structured_output'] as $line) {
                    $color = $line['type'] === 'code' ? 'cyan' : ($line['type'] === 'error' ? 'red' : 'white');
                    $this->line("    <fg=$color>" . ($line['type'] === 'code' ? '> ' : '  ') . $line['content'] . "</>");
                }
            } else {
                $this->error("  [FAIL] R ran but structured_output is EMPTY");
                $this->line("  Raw Output: " . $content->output);
            }

            if (isset($content->result['variables']) && count($content->result['variables']) > 0) {
                $this->info("  [OK] Variables captured (" . count($content->result['variables']) . ")");
                foreach ($content->result['variables'] as $var) {
                    $this->line("    <fg=yellow>{$var['name']}</> ({$var['type']}): " . \Illuminate\Support\Str::limit($var['value'], 40));
                }
            }
        } else {
            $this->error("  [FAIL] Unexpected response type: " . $content->type);
            $this->line("  Raw Output: " . ($content->output ?? 'NULL'));
        }
    }

    protected function header($text)
    {
        $this->line(str_repeat("=", 50));
        $this->info($text);
        $this->line(str_repeat("=", 50));
    }
}
