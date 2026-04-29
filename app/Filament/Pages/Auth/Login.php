<?php

namespace App\Filament\Pages\Auth;

use Filament\Auth\Pages\Login as BaseLogin;
use Illuminate\Contracts\View\View;

class Login extends BaseLogin
{
    public function getFooter(): ?View
    {
        return view('filament.auth.login-footer');
    }
}
