<?php

declare(strict_types=1);

use Oki\People\Exceptions\PeopleRefused;
use Oki\People\IntrospectedToken;

/** @return array<string, mixed> */
function live_token(): array
{
    return [
        'active' => true,
        'scope' => 'openid profile',
        'client_id' => CLIENT,
        'username' => 'ada',
        'token_type' => 'Bearer',
        'sub' => 'user-1',
        'aud' => [CLIENT, AUDIENCE],
        'iss' => ISSUER,
        'exp' => 1793000000,
        'jti' => 'token-1',
    ];
}

beforeEach(function () {
    $this->stage = stage();
    $this->stage->http->on('/oauth/token', ['access_token' => 'client-token', 'expires_in' => 900]);
});

it('reads what People says about a live token, on the client\'s own token', function () {
    $this->stage->http->on('/oauth/introspect', live_token());

    $answer = $this->stage->people->introspection()->of('somebody-elses-token');
    $asked = $this->stage->http->last('/oauth/introspect');

    expect($answer->active)->toBeTrue()
        ->and($answer->scopes)->toBe(['openid', 'profile'])
        ->and($answer->clientId)->toBe(CLIENT)
        ->and($answer->subject)->toBe('user-1')
        ->and($answer->username)->toBe('ada')
        ->and($answer->audiences)->toBe([CLIENT, AUDIENCE])
        ->and($answer->issuer)->toBe(ISSUER)
        ->and($answer->expiresAt?->getTimestamp())->toBe(1793000000)
        ->and($answer->id)->toBe('token-1')
        ->and($asked?->getHeaderLine('Authorization'))->toBe('Bearer client-token')
        ->and(form_of($asked)['token'])->toBe('somebody-elses-token')
        ->and(form_of($this->stage->http->last('/oauth/token'))['scope'])->toBe('tokens.introspect');
});

it('says a token is not active, and holds nothing else about it', function () {
    $this->stage->http->on('/oauth/introspect', ['active' => false]);

    $answer = $this->stage->people->introspection()->of('a-revoked-token');

    expect($answer->active)->toBeFalse()
        ->and($answer->scopes)->toBe([])
        ->and($answer->subject)->toBeNull()
        ->and($answer->expiresAt)->toBeNull()
        ->and($answer->can('openid'))->toBeFalse();
});

it('answers whether a live token was granted something', function () {
    $this->stage->http->on('/oauth/introspect', live_token());

    $answer = $this->stage->people->introspection()->of('a-token');

    expect($answer->can('profile'))->toBeTrue()->and($answer->can('payments.write'))->toBeFalse();
});

it('reads a single audience as a list of one', function () {
    $this->stage->http->on('/oauth/introspect', ['active' => true, 'aud' => AUDIENCE]);

    expect($this->stage->people->introspection()->of('a-token')->audiences)->toBe([AUDIENCE]);
});

it('tries once more on a fresh token when its held one is refused', function () {
    $this->stage->http->sequence('/oauth/introspect', [
        [401, ['message' => 'Unauthenticated.']],
        [200, live_token()],
    ]);

    expect($this->stage->people->introspection()->of('a-token')->active)->toBeTrue()
        ->and($this->stage->http->asked('/oauth/token'))->toBe(2);
});

it('does not retry a refusal that is not about its own token', function () {
    $this->stage->http->on('/oauth/introspect', ['message' => 'This endpoint is for this estate\'s own applications.'], 403);

    $this->stage->people->introspection()->of('a-token');
})->throws(PeopleRefused::class);

it('reads an answer with nothing in it as not active', function () {
    expect(IntrospectedToken::from([])->active)->toBeFalse();
});
