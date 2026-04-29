<?php

namespace App\Filament\Pages;

use App\Jobs\CompileDocumentJob;
use App\Jobs\ExecuteRCodeJob;
use App\Models\Node;
use App\Models\Project;
use App\Models\User;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;

class Editor extends Page
{
    protected string $view = 'filament.pages.editor';

    protected static bool $shouldRegisterNavigation = false;

    #[Locked]
    public int $projectId;

    public ?Node $activeNode = null;

    public string $activeCompiler = 'pdflatex';

    #[Url]
    public ?int $nodeId = null;

    public function mount(?int $project = null): void
    {
        $this->projectId = $project ?? (int) request()->query('project');
        abort_unless($this->projectId, 404);
        $this->authorize('view', $this->project);

        if ($this->nodeId) {
            $this->activeNode = Node::where('project_id', $this->projectId)
                ->where('id', $this->nodeId)
                ->firstOrFail();
        }
    }

    #[Computed]
    public function project(): Project
    {
        return Project::with(['nodes.children', 'shares'])->findOrFail($this->projectId);
    }

    public function getTitle(): string|Htmlable
    {
        return $this->project->name;
    }

    public function openNode(int $nodeId): void
    {
        $node = Node::where('project_id', $this->projectId)
            ->where('id', $nodeId)
            ->where('type', 'file')
            ->firstOrFail();

        $this->activeNode = $node;
        $this->nodeId = $node->id;
    }

    #[On('save-content')]
    public function saveContent(string $content): void
    {
        if (! $this->activeNode) {
            return;
        }

        $this->authorize('update', $this->project);
        $this->activeNode->update(['content' => $content]);
    }

    public function createNode(string $name, ?int $parentId, string $type): void
    {
        $this->authorize('update', $this->project);

        $node = $this->project->nodes()->create([
            'parent_id' => $parentId,
            'type' => $type,
            'name' => $name,
        ]);

        unset($this->project);

        if ($type === 'file') {
            $this->openNode($node->id);
        }
    }

    public function deleteNode(int $nodeId): void
    {
        $node = Node::where('project_id', $this->projectId)->findOrFail($nodeId);
        $this->authorize('update', $this->project);

        if ($this->activeNode?->id === $nodeId) {
            $this->activeNode = null;
            $this->nodeId = null;
        }

        $node->delete();
        unset($this->project);
    }

    public function renameNode(int $nodeId, string $name): void
    {
        $node = Node::where('project_id', $this->projectId)->findOrFail($nodeId);
        $this->authorize('update', $this->project);
        $node->update(['name' => $name]);
        unset($this->project);

        if ($this->activeNode?->id === $nodeId) {
            $this->activeNode->name = $name;
        }
    }

    public function moveNode(int $nodeId, ?int $newParentId): void
    {
        $node = Node::where('project_id', $this->projectId)->findOrFail($nodeId);
        $this->authorize('update', $this->project);
        $node->update(['parent_id' => $newParentId]);
        unset($this->project);
    }

    public function uploadFile(string $name, string $content, ?int $parentId): void
    {
        $this->authorize('update', $this->project);

        $node = $this->project->nodes()->create([
            'parent_id' => $parentId,
            'type' => 'file',
            'name' => $name,
            'content' => $content,
        ]);

        unset($this->project);
        $this->openNode($node->id);
    }

    public function shareNode(int $nodeId, bool $isPublic, string $permission, array $users = []): void
    {
        $node = Node::where('project_id', $this->projectId)->findOrFail($nodeId);
        $this->authorize('update', $this->project);

        $node->shares()->delete();

        if ($isPublic) {
            $node->shares()->create(['is_public' => true, 'permission' => $permission]);
        } else {
            foreach ($users as $entry) {
                $user = User::where('email', $entry['email'])->first();
                if ($user) {
                    $node->shares()->create(['user_id' => $user->id, 'permission' => $entry['permission']]);
                }
            }
        }

        Notification::make()->title('Deelinstelling opgeslagen')->success()->send();
    }

    #[On('compile')]
    public function compile(): void
    {
        if (! $this->activeNode?->isCompilable()) {
            return;
        }

        $this->authorize('update', $this->project);

        CompileDocumentJob::dispatch(
            $this->activeNode,
            auth()->user(),
            $this->activeCompiler
        );

        Notification::make()
            ->title('Compilatie gestart')
            ->info()
            ->send();
    }

    #[On('execute-r')]
    public function executeR(string $code): void
    {
        if (! $this->activeNode?->isExecutable()) {
            return;
        }

        $this->authorize('update', $this->project);

        ExecuteRCodeJob::dispatch(
            $this->activeNode,
            auth()->user(),
            $code
        );
    }

    public function pollUpdates(): void
    {
        $userId = auth()->id();

        $rOutput = Cache::pull("r_output_{$userId}", []);
        foreach ($rOutput as $entry) {
            $this->dispatch('r-output', ...$entry);
        }

        $rVars = Cache::get("r_vars_{$userId}");
        if ($rVars !== null) {
            $this->dispatch('r-variables', $rVars);
        }

        $rPlots = Cache::pull("r_plots_{$userId}", []);
        foreach ($rPlots as $plot) {
            $this->dispatch('r-plot', $plot);
        }

        if ($this->activeNode?->isCompilable()) {
            $latestLog = $this->activeNode->compileLogs()->latest()->first();
            if ($latestLog?->isSuccessful() && $latestLog->pdf_path) {
                $this->dispatch('pdf-ready', url: Storage::url($latestLog->pdf_path));
            }
        }
    }
}
