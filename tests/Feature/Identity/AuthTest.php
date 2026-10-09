<?php

use App\Modules\Identity\Database\Seeders\RolePermissionSeeder;
use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

it('registers a customer with the customer role', function () {
    $response = $this->postJson('/api/v1/auth/register', [
        'name' => 'Alice',
        'email' => 'alice@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.user.email', 'alice@example.com')
        ->assertJsonPath('data.user.roles.0', 'customer')
        ->assertJsonStructure(['data' => ['user', 'token'], 'meta' => ['request_id']]);

    expect(User::where('email', 'alice@example.com')->first()->hasRole(Role::Customer->value))->toBeTrue();
});

it('rejects registration with an invalid payload', function () {
    $response = $this->postJson('/api/v1/auth/register', [
        'name' => '',
        'email' => 'not-an-email',
        'password' => 'short',
    ]);

    $response->assertStatus(422)
        ->assertJsonPath('error.code', 'VALIDATION_FAILED')
        ->assertJsonStructure(['error' => ['code', 'message', 'details' => ['fields'], 'request_id']]);
});

it('logs in and returns a token', function () {
    User::factory()->create([
        'email' => 'bob@example.com',
        'password' => 'password123',
    ]);

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'bob@example.com',
        'password' => 'password123',
    ]);

    $response->assertOk()
        ->assertJsonPath('data.user.email', 'bob@example.com')
        ->assertJsonStructure(['data' => ['user', 'token']]);
});

it('rejects invalid credentials', function () {
    User::factory()->create([
        'email' => 'bob@example.com',
        'password' => 'password123',
    ]);

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'bob@example.com',
        'password' => 'wrong-password',
    ]);

    $response->assertStatus(422)->assertJsonPath('error.code', 'VALIDATION_FAILED');
});

it('requires authentication for the current user endpoint', function () {
    $this->getJson('/api/v1/auth/me')
        ->assertStatus(401)
        ->assertJsonPath('error.code', 'UNAUTHENTICATED');
});

it('returns the authenticated user', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $this->getJson('/api/v1/auth/me')
        ->assertOk()
        ->assertJsonPath('data.id', $user->id);
});

it('revokes the current token on logout', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $this->postJson('/api/v1/auth/logout')->assertOk();

    expect($user->tokens()->count())->toBe(0);
});
