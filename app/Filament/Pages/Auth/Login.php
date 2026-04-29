<?php

namespace App\Filament\Pages\Auth;

use Filament\Pages\SimplePage;
use Illuminate\Contracts\Support\Htmlable;

class Login extends SimplePage
{
    protected string $view = 'filament.auth.login';

    public function getTitle(): string|Htmlable
    {
        return config('app.name');
    }
}
