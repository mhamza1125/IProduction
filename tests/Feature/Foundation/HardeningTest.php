<?php

use Illuminate\Support\Facades\DB;

it('sends browser hardening headers on every page', function () {
    $response = $this->get('/login');

    $response->assertOk()
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
        ->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=()');
    expect($response->headers->has('Strict-Transport-Security'))->toBeFalse();   // only over HTTPS in production
});

it('reports healthy at /up when the database and storage work, and unhealthy when the database is down', function () {
    $this->get('/up')->assertOk();

    config(['database.connections.broken' => ['driver' => 'mysql', 'host' => '127.0.0.1', 'port' => 1, 'database' => 'none_test', 'username' => 'x', 'password' => 'x']]);
    config(['database.default' => 'broken']);
    DB::purge('broken');

    $this->get('/up')->assertStatus(500);
});
