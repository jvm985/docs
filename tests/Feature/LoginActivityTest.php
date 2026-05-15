<?php

use App\Models\LoginActivity;
use App\Models\User;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Event;

test('login event creates an activity row', function () {
    $user = User::factory()->create();

    event(new Login('web', $user, false));

    expect(LoginActivity::count())->toBe(1);
    $row = LoginActivity::first();
    expect($row->user_id)->toBe($user->id);
    expect($row->created_at)->not->toBeNull();
});

test('actingAs does not write to the activity log', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/projects')->assertOk();

    expect(LoginActivity::count())->toBe(0);
});

test('admin can view the activity page', function () {
    $admin = User::factory()->admin()->create();
    $other = User::factory()->create(['name' => 'Loggy Logger']);
    event(new Login('web', $other, false));

    $this->actingAs($admin)->get('/admin/activity')
        ->assertOk()
        ->assertSee('Activiteit')
        ->assertSee('Loggy Logger');
});

test('teacher cannot view the activity page', function () {
    $teacher = User::factory()->teacher()->create();

    $this->actingAs($teacher)->get('/admin/activity')->assertForbidden();
});

test('student cannot view the activity page', function () {
    $student = User::factory()->create();

    $this->actingAs($student)->get('/admin/activity')->assertForbidden();
});

test('only the latest 100 activities are shown', function () {
    $admin = User::factory()->admin()->create();
    $u = User::factory()->create();

    // Create 105 activity rows
    LoginActivity::factory()->count(105)->create(['user_id' => $u->id]);

    $resp = $this->actingAs($admin)->get('/admin/activity')->assertOk();
    $count = substr_count($resp->getContent(), 'data-testid="activity-row"');
    expect($count)->toBe(100);
});

test('activity page is empty when no logins recorded', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->get('/admin/activity')
        ->assertOk()
        ->assertSee('Nog geen logins geregistreerd');
});

test('admin sidebar shows Activiteit link only to admins', function () {
    $admin = User::factory()->admin()->create();
    $student = User::factory()->create();

    $this->actingAs($admin)->get('/projects')->assertSee('Activiteit');
    $this->actingAs($student)->get('/projects')->assertDontSee('Activiteit');
});
