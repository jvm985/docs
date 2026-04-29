<?php

namespace App\Models;

use Database\Factories\NodeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

#[Fillable(['project_id', 'parent_id', 'type', 'name', 'content', 'disk_path'])]
class Node extends Model
{
    /** @use HasFactory<NodeFactory> */
    use HasFactory;

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Node::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Node::class, 'parent_id')->orderBy('type')->orderBy('name');
    }

    public function compileLogs(): HasMany
    {
        return $this->hasMany(CompileLog::class)->orderByDesc('created_at');
    }

    public function shares(): MorphMany
    {
        return $this->morphMany(Share::class, 'shareable');
    }

    public function isFolder(): bool
    {
        return $this->type === 'folder';
    }

    public function isFile(): bool
    {
        return $this->type === 'file';
    }

    public function extension(): string
    {
        return strtolower(pathinfo($this->name, PATHINFO_EXTENSION));
    }

    public function editorLanguage(): string
    {
        return match ($this->extension()) {
            'tex' => 'latex',
            'md' => 'markdown',
            'rmd' => 'markdown',
            'r' => 'r',
            'json' => 'json',
            'xml' => 'xml',
            'txt' => 'plaintext',
            'typ' => 'plaintext',
            default => 'plaintext',
        };
    }

    public function isCompilable(): bool
    {
        return in_array($this->extension(), ['tex', 'md', 'rmd', 'typ']);
    }

    public function isExecutable(): bool
    {
        return $this->extension() === 'r';
    }
}
