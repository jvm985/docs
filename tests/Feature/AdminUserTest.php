<?php

use App\Models\User;

test('admin can access the role management page', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->get('/admin/users')
        ->assertOk()
        ->assertSee('Beheer rollen');
});

test('teacher cannot access the role management page', function () {
    $teacher = User::factory()->teacher()->create();

    $this->actingAs($teacher)->get('/admin/users')->assertForbidden();
});

test('student cannot access the role management page', function () {
    $student = User::factory()->create();

    $this->actingAs($student)->get('/admin/users')->assertForbidden();
});

test('admin can change another user role to teacher', function () {
    $admin = User::factory()->admin()->create();
    $target = User::factory()->create();

    $this->actingAs($admin)
        ->patch("/admin/users/{$target->id}/role", ['role' => 'teacher'])
        ->assertRedirect();

    expect($target->fresh()->role)->toBe('teacher');
});

test('admin can promote a user to admin', function () {
    $admin = User::factory()->admin()->create();
    $target = User::factory()->create();

    $this->actingAs($admin)
        ->patch("/admin/users/{$target->id}/role", ['role' => 'admin'])
        ->assertRedirect();

    expect($target->fresh()->role)->toBe('admin');
});

test('admin cannot demote themselves', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->patch("/admin/users/{$admin->id}/role", ['role' => 'student'])
        ->assertRedirect();

    expect($admin->fresh()->role)->toBe('admin');
});

test('non-admin cannot change roles', function () {
    $teacher = User::factory()->teacher()->create();
    $target = User::factory()->create();

    $this->actingAs($teacher)
        ->patch("/admin/users/{$target->id}/role", ['role' => 'teacher'])
        ->assertForbidden();

    expect($target->fresh()->role)->toBe('student');
});

test('role validation rejects unknown values', function () {
    $admin = User::factory()->admin()->create();
    $target = User::factory()->create();

    $this->actingAs($admin)
        ->patch("/admin/users/{$target->id}/role", ['role' => 'superadmin'])
        ->assertSessionHasErrors('role');

    expect($target->fresh()->role)->toBe('student');
});

test('admin search filters users by name or email', function () {
    $admin = User::factory()->admin()->create();
    User::factory()->create(['name' => 'Alice Examply', 'email' => 'alice@example.com']);
    User::factory()->create(['name' => 'Bob Builder', 'email' => 'bob@example.com']);

    $resp = $this->actingAs($admin)->get('/admin/users?q=Alice');
    $resp->assertOk()->assertSee('Alice Examply')->assertDontSee('Bob Builder');
});

test('admin sidebar link is visible only to admins', function () {
    $admin = User::factory()->admin()->create();
    $student = User::factory()->create();

    $this->actingAs($admin)->get('/projects')
        ->assertSee('Beheer rollen');

    $this->actingAs($student)->get('/projects')
        ->assertDontSee('Beheer rollen');
});

test('admin is treated as teacher for shared drives', function () {
    $admin = User::factory()->admin()->create();

    expect($admin->isTeacher())->toBeTrue();
    expect($admin->isAdmin())->toBeTrue();

    $this->actingAs($admin)
        ->post('/drives', ['name' => 'Admin-drive'])
        ->assertRedirect();

    expect(\App\Models\SharedDrive::where('owner_id', $admin->id)->where('name', 'Admin-drive')->exists())->toBeTrue();
});
