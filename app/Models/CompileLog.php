<?php

namespace App\Models;

use Database\Factories\CompileLogFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['node_id', 'user_id', 'compiler', 'status', 'output', 'pdf_path'])]
class CompileLog extends Model
{
    /** @use HasFactory<CompileLogFactory> */
    use HasFactory;

    public function node(): BelongsTo
    {
        return $this->belongsTo(Node::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isSuccessful(): bool
    {
        return $this->status === 'success';
    }
}
