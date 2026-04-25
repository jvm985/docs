<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['project_id', 'parent_id', 'name', 'type', 'extension', 'content', 'binary_content', 'preferred_compiler'])]
class File extends Model
{
    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function parent()
    {
        return $this->belongsTo(File::class, 'parent_id');
    }

    public function getPath(): string
    {
        $path = [$this->name];
        $current = $this;

        while ($current->parent) {
            $current = $current->parent;
            array_unshift($path, $current->name);
        }

        return implode('/', $path);
    }
}
