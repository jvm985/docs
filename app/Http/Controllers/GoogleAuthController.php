<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;

class GoogleAuthController extends Controller
{
    public function redirectToGoogle()
    {
        return Socialite::driver('google')->redirect();
    }

    public function handleGoogleCallback()
    {
        try {
            $googleUser = Socialite::driver('google')->user();
            
            \Log::info('Google Login Callback Received', [
                'email' => $googleUser->email,
                'name' => $googleUser->name,
                'id' => $googleUser->id
            ]);

            $user = User::updateOrCreate([
                'email' => $googleUser->email,
            ], [
                'google_id' => $googleUser->id,
                'name' => $googleUser->name,
                'google_token' => $googleUser->token,
                'google_refresh_token' => $googleUser->refreshToken,
                'email_verified_at' => now(),
            ]);

            \Log::info('User record prepared', ['user_id' => $user->id]);

            Auth::login($user, true);
            
            \Log::info('Auth::login completed', [
                'check' => Auth::check(),
                'id' => Auth::id()
            ]);

            return redirect()->intended('dashboard');
        } catch (\Exception $e) {
            \Log::error('Google Login Exception', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            return redirect('login')->with('error', 'Google login failed: ' . $e->getMessage());
        }
    }
}
