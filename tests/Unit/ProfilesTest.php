<?php

declare(strict_types=1);

use Oki\People\Exceptions\PeopleRefused;
use Oki\People\Exceptions\PeopleUnreachable;
use Oki\People\Tests\Support\FakePeople;

it('reads what somebody shows under their handle, on no credential', function () {
    $stage = stage();
    $stage->http->on('/v1/profiles/ada', ['data' => [
        'id' => 'user-1',
        'username' => 'ada',
        'name' => 'Ada Lovelace',
        'person' => ['id' => 'person-1', 'name' => 'Ada Lovelace'],
        'joined_at' => '2026-01-04T09:00:00+00:00',
        'is_online' => true,
        'last_seen_at' => '2026-11-19T08:00:00+00:00',
    ]]);

    $profile = $stage->people->profiles()->of('ada');

    expect($profile?->id)->toBe('user-1')
        ->and($profile?->username)->toBe('ada')
        ->and($profile?->person?->id)->toBe('person-1')
        ->and($profile?->joinedAt?->format('c'))->toBe('2026-01-04T09:00:00+00:00')
        ->and($profile?->isOnline)->toBeTrue()
        ->and($profile?->lastSeenAt?->format('c'))->toBe('2026-11-19T08:00:00+00:00')
        ->and($stage->http->last('/v1/profiles/ada')?->hasHeader('Authorization'))->toBeFalse();
});

it('answers nothing where nobody holds the handle', function () {
    $stage = stage();
    $stage->http->on('/v1/profiles/nobody', ['message' => 'Not found.'], 404);

    expect($stage->people->profiles()->of('nobody'))->toBeNull();
});

it('says so when a profile is refused', function () {
    $stage = stage();
    $stage->http->on('/v1/profiles/ada', ['message' => 'This action is unauthorized.'], 403);

    $stage->people->profiles()->of('ada');
})->throws(PeopleRefused::class);

it('says so when People answers a profile with no profile', function () {
    $stage = stage();
    $stage->http->on('/v1/profiles/ada', ['data' => ['username' => 'ada']]);

    $stage->people->profiles()->of('ada');
})->throws(PeopleUnreachable::class, 'People answered with no profile.');

it('says so when People cannot be reached about a profile', function () {
    $stage = stage();
    $stage->http->down('/v1/profiles/ada');

    $stage->people->profiles()->of('ada');
})->throws(PeopleUnreachable::class);

it('passes over a profile answer that is not one', function () {
    $stage = stage();
    $stage->http->on('/v1/profiles/ada', fn () => FakePeople::json(['data' => ['id' => 'user-1', 'person' => 'nonsense', 'joined_at' => 'never']]));

    $profile = $stage->people->profiles()->of('ada');

    expect($profile?->person)->toBeNull()
        ->and($profile?->joinedAt)->toBeNull();
});
