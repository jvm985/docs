<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['project_id', 'parent_id', 'name', 'type', 'extension', 'content', 'binary_content'])]
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

    public function children()
    {
        return $this->hasMany(File::class, 'parent_id');
    }
}
