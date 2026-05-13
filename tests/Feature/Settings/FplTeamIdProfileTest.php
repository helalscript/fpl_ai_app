<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('profile can store optional fpl team id', function () {
    $user = User::factory()->create([
        'fpl_team_id' => null,
    ]);

    $this->actingAs($user)
        ->from(route('dashboard'))
        ->patch(route('profile.update'), [
            'name' => $user->name,
            'email' => $user->email,
            'fpl_team_id' => 42,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('dashboard'));

    expect($user->refresh()->fpl_team_id)->toBe(42);
});

test('profile rejects invalid fpl team id', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->from(route('profile.edit'))
        ->patch(route('profile.update'), [
            'name' => $user->name,
            'email' => $user->email,
            'fpl_team_id' => 0,
        ])
        ->assertSessionHasErrors('fpl_team_id');
});
