<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CompileFileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->can('view', $this->file->project);
    }

    public function rules(): array
    {
        return [
            'compiler' => 'nullable|string|in:pdflatex,xelatex,lualatex,bibtex,biber',
            'code' => 'nullable|string',
        ];
    }
}
