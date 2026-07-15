<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('prevents creating a second unit head account', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    User::factory()->create(['role' => User::ROLE_UNIT_HEAD]);

    $response = $this->actingAs($admin)->post(route('admin.users.store'), [
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'role' => User::ROLE_UNIT_HEAD,
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $response->assertSessionHasErrorsIn('add', 'role');
    $this->assertDatabaseCount('users', 2);
    $this->assertDatabaseMissing('users', ['email' => 'jane@example.com']);
});
