<?php

use App\Models\User;

it('redirects guests to the login page', function () {
    $this->get('/dashboard')->assertRedirect('/login');
});

it('shows the login page', function () {
    $this->get('/login')->assertOk()->assertSee('Sign in');
});

it('signs in an active user and records the login time', function () {
    $user = User::factory()->create();

    $this->post('/login', ['login' => $user->email, 'password' => 'password'])
        ->assertRedirect('/dashboard');

    $this->assertAuthenticatedAs($user);
    expect($user->fresh()->last_login_at)->not->toBeNull();
});

it('signs in with a username instead of an email', function () {
    $user = User::factory()->create(['username' => 'store.keeper']);

    $this->post('/login', ['login' => 'store.keeper', 'password' => 'password'])->assertRedirect('/dashboard');

    $this->assertAuthenticatedAs($user);
});

it('rejects a wrong password', function () {
    $user = User::factory()->create();

    $this->post('/login', ['login' => $user->email, 'password' => 'wrong'])->assertSessionHasErrors('login');

    $this->assertGuest();
});

it('does not let a deactivated user sign in', function () {
    $user = User::factory()->inactive()->create();

    $this->post('/login', ['login' => $user->email, 'password' => 'password'])
        ->assertSessionHasErrors(['login' => 'This account has been deactivated. Contact your administrator.']);

    $this->assertGuest();
});

it('has public registration turned off', function () {
    $this->get('/register')->assertNotFound();
});

it('renders the dashboard and profile pages', function () {
    // fresh() loads every column, as a real request would.
    $this->actingAs(User::factory()->create()->fresh());

    $this->get('/dashboard')->assertOk()->assertSee('Dashboard');
    $this->get('/profile')->assertOk()->assertSee('Two-factor authentication');
});

it('hides the demo login unless it is turned on', function () {
    config(['iproduction.demo_login.enabled' => false]);

    $this->get('/login')->assertDontSee('Sign in as demo admin');
    $this->post('/demo-login')->assertNotFound();
    $this->assertGuest();
});

it('signs in the demo account with one click when turned on', function () {
    $user = User::factory()->create(['email' => 'demo@example.com']);
    config(['iproduction.demo_login' => ['enabled' => true, 'email' => 'demo@example.com']]);

    $this->get('/login')->assertSee('Sign in as demo admin');
    $this->post('/demo-login')->assertRedirect('/dashboard');

    $this->assertAuthenticatedAs($user);
    expect($user->fresh()->last_login_at)->not->toBeNull();
});

it('does not demo-login a deactivated account', function () {
    User::factory()->inactive()->create(['email' => 'demo@example.com']);
    config(['iproduction.demo_login' => ['enabled' => true, 'email' => 'demo@example.com']]);

    $this->post('/demo-login')->assertSessionHasErrors('login');
    $this->assertGuest();
});
