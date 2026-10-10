<?php

use App\Modules\Identity\Database\Seeders\RolePermissionSeeder;
use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Support\Totp;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->totp = app(Totp::class);

    $this->enableMfa = function (User $user): string {
        $secret = $this->totp->generateSecret();
        $user->forceFill(['two_factor_secret' => $secret, 'two_factor_confirmed_at' => now()])->save();

        return $secret;
    };
});

it('enrols and confirms two-factor authentication', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $enable = $this->postJson('/api/v1/auth/two-factor/enable')
        ->assertOk()
        ->assertJsonStructure(['data' => ['secret', 'otpauth_uri']]);

    $secret = $enable->json('data.secret');

    $confirm = $this->postJson('/api/v1/auth/two-factor/confirm', [
        'code' => $this->totp->code($secret),
    ])->assertOk()->assertJsonStructure(['data' => ['recovery_codes']]);

    expect($confirm->json('data.recovery_codes'))->toHaveCount(8)
        ->and($user->refresh()->hasTwoFactorEnabled())->toBeTrue();
});

it('reports two_factor_enabled on the current user', function () {
    $user = User::factory()->create();
    ($this->enableMfa)($user);
    Sanctum::actingAs($user);

    $this->getJson('/api/v1/auth/me')
        ->assertOk()
        ->assertJsonPath('data.two_factor_enabled', true);
});

it('rejects confirmation with an invalid code', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);
    $this->postJson('/api/v1/auth/two-factor/enable')->assertOk();

    $this->postJson('/api/v1/auth/two-factor/confirm', ['code' => '000000'])
        ->assertStatus(401)
        ->assertJsonPath('error.code', 'TWO_FACTOR_INVALID_CODE');
});

it('rejects confirmation before enrolment has started', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $this->postJson('/api/v1/auth/two-factor/confirm', ['code' => '123456'])
        ->assertStatus(409)
        ->assertJsonPath('error.code', 'TWO_FACTOR_NOT_ENABLED');
});

it('rejects starting enrolment when two-factor is already enabled', function () {
    $user = User::factory()->create();
    ($this->enableMfa)($user);
    Sanctum::actingAs($user);

    $this->postJson('/api/v1/auth/two-factor/enable')
        ->assertStatus(409)
        ->assertJsonPath('error.code', 'TWO_FACTOR_ALREADY_ENABLED');
});

it('requires a second factor at login when enabled', function () {
    $user = User::factory()->create(['email' => 'bob@example.com', 'password' => 'password123']);
    $secret = ($this->enableMfa)($user);

    $this->postJson('/api/v1/auth/login', [
        'email' => 'bob@example.com',
        'password' => 'password123',
    ])->assertStatus(401)->assertJsonPath('error.code', 'MFA_REQUIRED');

    $this->postJson('/api/v1/auth/login', [
        'email' => 'bob@example.com',
        'password' => 'password123',
        'two_factor_code' => '000000',
    ])->assertStatus(401)->assertJsonPath('error.code', 'TWO_FACTOR_INVALID_CODE');

    $this->postJson('/api/v1/auth/login', [
        'email' => 'bob@example.com',
        'password' => 'password123',
        'two_factor_code' => $this->totp->code($secret),
    ])->assertOk()->assertJsonStructure(['data' => ['token']]);
});

it('accepts a valid second factor at login within the drift window', function () {
    $user = User::factory()->create(['email' => 'bob@example.com', 'password' => 'password123']);
    $secret = ($this->enableMfa)($user);

    $code = $this->totp->code($secret, time() + 30);

    $this->postJson('/api/v1/auth/login', [
        'email' => 'bob@example.com',
        'password' => 'password123',
        'two_factor_code' => $code,
    ])->assertOk();
});

it('accepts a single-use recovery code and consumes it', function () {
    $user = User::factory()->create(['email' => 'bob@example.com', 'password' => 'password123']);
    Sanctum::actingAs($user);
    $secret = ($this->enableMfa)($user);
    $this->postJson('/api/v1/auth/two-factor/enable')->assertStatus(409);

    $user->forceFill(['two_factor_recovery_codes' => [hash('sha256', 'AAAA-BBBB')]])->save();

    $this->postJson('/api/v1/auth/login', [
        'email' => 'bob@example.com',
        'password' => 'password123',
        'two_factor_code' => 'AAAA-BBBB',
    ])->assertOk();

    $this->postJson('/api/v1/auth/login', [
        'email' => 'bob@example.com',
        'password' => 'password123',
        'two_factor_code' => 'AAAA-BBBB',
    ])->assertStatus(401)->assertJsonPath('error.code', 'TWO_FACTOR_INVALID_CODE');

    expect($user->refresh()->two_factor_recovery_codes)->toBe([]);
});

it('disables two-factor with a valid code', function () {
    $user = User::factory()->create(['email' => 'bob@example.com', 'password' => 'password123']);
    $secret = ($this->enableMfa)($user);
    Sanctum::actingAs($user);

    $this->postJson('/api/v1/auth/two-factor/disable', ['code' => '000000'])
        ->assertStatus(401)
        ->assertJsonPath('error.code', 'TWO_FACTOR_INVALID_CODE');

    $this->postJson('/api/v1/auth/two-factor/disable', ['code' => $this->totp->code($secret)])
        ->assertOk();

    expect($user->refresh()->hasTwoFactorEnabled())->toBeFalse();

    $this->postJson('/api/v1/auth/login', [
        'email' => 'bob@example.com',
        'password' => 'password123',
    ])->assertOk();
});

it('regenerates recovery codes with a valid second factor', function () {
    $user = User::factory()->create();
    $secret = ($this->enableMfa)($user);
    Sanctum::actingAs($user);

    $this->postJson('/api/v1/auth/two-factor/recovery-codes', ['code' => $this->totp->code($secret)])
        ->assertOk()
        ->assertJsonStructure(['data' => ['recovery_codes']]);
});

it('enforces two-factor for privileged staff when enabled by config', function () {
    config(['security.two_factor.admin_required' => true]);

    $admin = User::factory()->create();
    $admin->assignRole(Role::Admin->value);
    Sanctum::actingAs($admin);

    $this->getJson('/api/v1/admin/wallets')
        ->assertStatus(401)
        ->assertJsonPath('error.code', 'MFA_REQUIRED');

    ($this->enableMfa)($admin);

    $this->getJson('/api/v1/admin/wallets')->assertOk();
});

it('does not require two-factor for customers', function () {
    config(['security.two_factor.admin_required' => true]);

    $customer = User::factory()->create();
    $customer->assignRole(Role::Customer->value);
    Sanctum::actingAs($customer);

    $this->getJson('/api/v1/auth/me')->assertOk();
});
