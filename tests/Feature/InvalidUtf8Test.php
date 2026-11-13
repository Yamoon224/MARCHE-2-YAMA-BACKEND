<?php

declare(strict_types=1);

it('rejects a non UTF-8 body with 422 instead of a server error', function () {
    $response = $this->call('POST', '/api/v1/auth/login', ['email' => "a\xE9@x.test", 'password' => 'x']);

    $response->assertStatus(422)->assertJsonPath('errors.encoding.0', 'Les données doivent être encodées en UTF-8.');
});

it('rejects a non UTF-8 query string', function () {
    $this->call('GET', '/api/v1/products', ['q' => "caf\xE9"])->assertStatus(422);
});

it('accepts valid UTF-8 text', function () {
    $this->call('GET', '/api/v1/products', ['q' => 'café'])->assertOk();
});
