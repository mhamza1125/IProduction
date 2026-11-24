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

    // Production behaviour: with debug on, Laravel rethrows health failures instead of answering 500.
    config(['app.debug' => false]);
    config(['database.connections.broken' => ['driver' => 'mysql', 'host' => '127.0.0.1', 'port' => 1, 'database' => 'none_test', 'username' => 'x', 'password' => 'x']]);
    $default = config('database.default');
    config(['database.default' => 'broken']);
    DB::purge('broken');

    try {
        $this->get('/up')->assertStatus(500);
    } finally {
        config(['database.default' => $default]);
    }
});
