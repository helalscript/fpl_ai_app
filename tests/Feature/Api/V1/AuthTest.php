<?php

use App\Mail\OtpMail;
use App\Models\User;
use App\Services\Api\V1\Auth\OtpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Laravel\Sanctum\PersonalAccessToken;

uses(RefreshDatabase::class);

test('api register sends otp and does not issue token until email is verified', function () {
    Mail::fake();

    $response = $this->postJson('/api/auth/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'fpl_team_id' => 123,
    ]);

    $response->assertCreated();
    $response->assertJsonPath('status', true);
    $response->assertJsonPath('code', 201);
    $response->assertJsonPath('data.user.is_otp_verified', false);
    $response->assertJsonMissingPath('data.token');

    Mail::assertSent(OtpMail::class, function (OtpMail $mail): bool {
        return $mail->purpose === 'registration';
    });
});

test('api verify email completes registration and returns token', function () {
    Mail::fake();

    $this->postJson('/api/auth/register', [
        'name' => 'Test User',
        'email' => 'verify@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $user = User::query()->where('email', 'verify@example.com')->firstOrFail();
    $otp = $user->otp;
    expect($otp)->not->toBeNull();

    $response = $this->postJson('/api/auth/verify-email', [
        'email' => 'verify@example.com',
        'otp' => $otp,
    ]);

    $response->assertOk();
    $response->assertJsonPath('status', true);
    $response->assertJsonPath('data.user.is_otp_verified', true);
    expect($response->json('data.token'))->toBeString();
});

test('api login returns 401 for wrong credentials', function () {
    User::factory()->create(['email' => 'test@example.com', 'password' => 'password']);

    $response = $this->postJson('/api/auth/login', [
        'email' => 'test@example.com',
        'password' => 'wrong',
    ]);

    $response->assertStatus(401);
    $response->assertJsonPath('status', false);
});

test('api login returns 403 when email is not verified', function () {
    User::factory()->unverified()->create([
        'email' => 'unverified@example.com',
        'password' => 'password',
    ]);

    $response = $this->postJson('/api/auth/login', [
        'email' => 'unverified@example.com',
        'password' => 'password',
    ]);

    $response->assertStatus(403);
    $response->assertJsonPath('status', false);
});

test('api login returns user and token for valid verified credentials', function () {
    User::factory()->create(['email' => 'test@example.com', 'password' => 'password']);

    $response = $this->postJson('/api/auth/login', [
        'email' => 'test@example.com',
        'password' => 'password',
    ]);

    $response->assertOk();
    $response->assertJsonPath('status', true);
    expect($response->json('data.token'))->toBeString();
});

test('api me requires sanctum token', function () {
    $response = $this->getJson('/api/auth/me');

    $response->assertStatus(401);
});

test('api me returns authenticated user', function () {
    $user = User::factory()->create();
    $token = $user->createToken('auth')->plainTextToken;

    $response = $this->withHeader('Authorization', 'Bearer '.$token)->getJson('/api/auth/me');

    $response->assertOk();
    $response->assertJsonPath('status', true);
    $response->assertJsonPath('data.user.email', $user->email);
});

test('api logout revokes current token', function () {
    $user = User::factory()->create();
    $token = $user->createToken('auth')->plainTextToken;

    $response = $this->withHeader('Authorization', 'Bearer '.$token)->postJson('/api/auth/logout');

    $response->assertOk();
    $response->assertJsonPath('status', true);
    expect($user->tokens()->count())->toBe(0);
});

test('api refresh token returns new token and invalidates old one', function () {
    $user = User::factory()->create();
    $oldToken = $user->createToken('auth')->plainTextToken;

    $response = $this->withHeader('Authorization', 'Bearer '.$oldToken)->postJson('/api/auth/refresh-token');

    $response->assertOk();
    $response->assertJsonPath('status', true);
    $newToken = $response->json('data.token');
    expect($newToken)->toBeString()->not->toBe($oldToken);

    expect(PersonalAccessToken::findToken($oldToken))->toBeNull();
    expect(PersonalAccessToken::findToken($newToken))->not->toBeNull();

    $this->withHeader('Authorization', 'Bearer '.$newToken)->getJson('/api/auth/me')->assertOk();
});

test('api forgot password responds success for existing email', function () {
    Mail::fake();

    $user = User::factory()->create(['email' => 'test@example.com']);

    Password::shouldReceive('sendResetLink')
        ->once()
        ->andReturn(Password::RESET_LINK_SENT);

    $response = $this->postJson('/api/auth/forget-password', [
        'email' => $user->email,
    ]);

    $response->assertOk();
    $response->assertJsonPath('status', true);

    Mail::assertSent(OtpMail::class, function (OtpMail $mail): bool {
        return $mail->purpose === 'password_reset';
    });
});

test('api resend otp sends otp mail for unverified user', function () {
    Mail::fake();

    $user = User::factory()->unverified()->create(['email' => 'resend@example.com']);

    $response = $this->postJson('/api/auth/resend-otp', [
        'email' => $user->email,
    ]);

    $response->assertOk();
    Mail::assertSent(OtpMail::class, function (OtpMail $mail): bool {
        return $mail->purpose === 'resend';
    });
});

test('api update profile requires sanctum token', function () {
    $response = $this->patchJson('/api/auth/profile', [
        'name' => 'New Name',
        'email' => 'new@example.com',
    ]);

    $response->assertStatus(401);
});

test('api update profile updates user', function () {
    $user = User::factory()->create(['name' => 'Old', 'email' => 'old@example.com']);
    $token = $user->createToken('auth')->plainTextToken;

    $response = $this->withHeader('Authorization', 'Bearer '.$token)->patchJson('/api/auth/profile', [
        'name' => 'New Name',
        'email' => 'new@example.com',
        'avatar' => 'https://example.com/a.png',
        'fpl_team_id' => 99,
    ]);

    $response->assertOk();
    $response->assertJsonPath('data.user.name', 'New Name');
    $response->assertJsonPath('data.user.email', 'new@example.com');
    $response->assertJsonPath('data.user.fpl_team_id', 99);
    expect($user->fresh()->email)->toBe('new@example.com');
});

test('api update password requires valid current password', function () {
    $user = User::factory()->create(['password' => 'old-password']);
    $token = $user->createToken('auth')->plainTextToken;

    $response = $this->withHeader('Authorization', 'Bearer '.$token)->putJson('/api/auth/password', [
        'current_password' => 'wrong',
        'password' => 'new-password-123',
        'password_confirmation' => 'new-password-123',
    ]);

    $response->assertStatus(422);
});

test('api update password updates password', function () {
    $user = User::factory()->create(['password' => 'old-password']);
    $token = $user->createToken('auth')->plainTextToken;

    $response = $this->withHeader('Authorization', 'Bearer '.$token)->putJson('/api/auth/password', [
        'current_password' => 'old-password',
        'password' => 'new-password-123',
        'password_confirmation' => 'new-password-123',
    ]);

    $response->assertOk();
    $response->assertJsonPath('status', true);

    $user->refresh();
    expect(Hash::check('new-password-123', $user->password))->toBeTrue();
});

test('api verify otp for password reset returns email and reset token', function () {
    $user = User::factory()->create(['email' => 'pwreset@example.com']);
    $otp = '111222';
    app(OtpService::class)->setOtp($user, $otp);

    $response = $this->postJson('/api/auth/verify-otp', [
        'email' => $user->email,
        'otp' => $otp,
    ]);

    $response->assertOk();
    $response->assertJsonPath('data.email', $user->email);
    expect($response->json('data.token'))->toBeString()->not->toBeEmpty();
});
