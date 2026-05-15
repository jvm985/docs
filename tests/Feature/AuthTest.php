<?php

use App\Models\User;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

test('homepage redirects to projects', function () {
    $this->get('/')->assertRedirect('/projects');
});

test('projects page is protected from guests', function () {
    $this->get('/projects')->assertRedirect('/login');
});

test('login page shows google button', function () {
    $this->get('/login')->assertSee('Aanmelden met Google');
});

test('google redirect route returns redirect', function () {
    $this->get(route('auth.google'))->assertRedirect();
});

test('google callback creates new user and logs in', function () {
    $socialiteUser = Mockery::mock(SocialiteUser::class);
    $socialiteUser->shouldReceive('getId')->andReturn('google-123');
    $socialiteUser->shouldReceive('getName')->andReturn('Test User');
    $socialiteUser->shouldReceive('getEmail')->andReturn('test@example.com');
    $socialiteUser->shouldReceive('getAvatar')->andReturn('https://example.com/avatar.jpg');

    Socialite::shouldReceive('driver->user')->andReturn($socialiteUser);

    $this->get(route('auth.google.callback'))
        ->assertRedirect('/projects');

    expect(User::where('google_id', 'google-123')->exists())->toBeTrue();
    $this->assertAuthenticated();
});

test('google callback updates existing user', function () {
    $user = User::factory()->create([
        'google_id' => 'google-456',
        'email' => 'existing@example.com',
    ]);

    $socialiteUser = Mockery::mock(SocialiteUser::class);
    $socialiteUser->shouldReceive('getId')->andReturn('google-456');
    $socialiteUser->shouldReceive('getName')->andReturn('Updated Name');
    $socialiteUser->shouldReceive('getEmail')->andReturn('existing@example.com');
    $socialiteUser->shouldReceive('getAvatar')->andReturn(null);

    Socialite::shouldReceive('driver->user')->andReturn($socialiteUser);

    $this->get(route('auth.google.callback'));

    expect($user->refresh()->name)->toBe('Updated Name');
});

test('authenticated user can access projects', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->get('/projects')->assertSuccessful();
});
