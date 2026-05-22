<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

it('redirects guests away from admin area', function () {
    $this->get('/admin')->assertRedirect('/login');
});

it('blocks regular users from admin area', function () {
    $user = User::factory()->create();
    $user->assignRole('user');

    $this->actingAs($user)->get('/admin')->assertForbidden();
});

it('allows admins into the admin area', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    // Stub upstream API call performed by AdminDashboardController.
    Http::fake([
        '*' => Http::response(['bValue' => '5.0'], 200),
    ]);

    $this->actingAs($admin)->get('/admin')->assertOk();
});
