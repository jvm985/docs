<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Services\RExecutionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class RController extends Controller
{
    public function __construct(private RExecutionService $r) {}

    public function execute(Request $request, Project $project)
    {
        Gate::authorize('view', $project);
        $data = $request->validate([
            'code' => ['required', 'string'],
            'path' => ['nullable', 'string'],
        ]);
        $result = $this->r->execute($project, $request->user(), $data['code'], $data['path'] ?? null);

        return response()->json($result);
    }

    public function executeStream(Request $request, Project $project)
    {
        Gate::authorize('view', $project);
        $data = $request->validate([
            'code' => ['required', 'string'],
            'path' => ['nullable', 'string'],
        ]);
        $user = $request->user();

        return response()->stream(function () use ($project, $user, $data) {
            @set_time_limit(0);
            ignore_user_abort(false);
            // Drop any output buffers Laravel/PHP may have stacked, then keep
            // stdout flushed implicitly so each echo reaches the wire.
            while (ob_get_level() > 0) {
                @ob_end_flush();
            }
            ob_implicit_flush(true);

            $send = function (array $msg): void {
                echo json_encode($msg)."\n";
                @flush();
            };
            $send(['kind' => 'started']);
            try {
                $result = $this->r->executeStreaming($project, $user, $data['code'], $data['path'] ?? null,
                    fn (array $entry) => $send(['kind' => 'entry', 'entry' => $entry]));
                $send(['kind' => 'done', 'variables' => $result['variables'], 'plots' => $result['plots']]);
            } catch (\Throwable $e) {
                $send(['kind' => 'done', 'error' => $e->getMessage(), 'variables' => [], 'plots' => []]);
            }
        }, 200, [
            'Content-Type' => 'application/x-ndjson',
            'X-Accel-Buffering' => 'no',
            'Cache-Control' => 'no-cache, no-transform',
            'Content-Encoding' => 'identity',
        ]);
    }

    public function inspect(Request $request, Project $project)
    {
        Gate::authorize('view', $project);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:128'],
        ]);

        return response()->json($this->r->inspect($project, $request->user(), $data['name']));
    }

    public function reset(Request $request, Project $project)
    {
        Gate::authorize('view', $project);
        $this->r->reset($project, $request->user());

        return response()->json(['ok' => true]);
    }

    public function state(Request $request, Project $project)
    {
        Gate::authorize('view', $project);

        return response()->json($this->r->state($project, $request->user()));
    }
}
