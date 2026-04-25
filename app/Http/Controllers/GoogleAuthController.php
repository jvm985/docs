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
            
            \Log::info('Google Login Attempt', ['email' => $googleUser->email]);

            $user = User::updateOrCreate([
                'google_id' => $googleUser->id,
            ], [
                'name' => $googleUser->name,
                'email' => $googleUser->email,
                'google_token' => $googleUser->token,
                'google_refresh_token' => $googleUser->refreshToken,
                'email_verified_at' => now(),
            ]);

            Auth::login($user, true);
            \Log::info('User logged in', ['id' => $user->id]);

            return redirect()->intended('dashboard');
        } catch (\Exception $e) {
            \Log::error('Google Login Error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return redirect('login')->with('error', 'Google login failed: ' . $e->getMessage());
        }
    }
}
