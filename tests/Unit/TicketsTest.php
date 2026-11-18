<?php

declare(strict_types=1);

use Oki\People\Config;
use Oki\People\Exceptions\PeopleRefused;
use Oki\People\Exceptions\PeopleUnreachable;
use Oki\People\Tests\Support\ArrayCache;
use Oki\People\Tests\Support\FakePeople;
use Oki\People\Tests\Support\FrozenClock;
use Oki\People\Tests\Support\Stage;

const TICKETS = '/v1/manage/identity/tickets';

it('mints a ticket on this service\'s own key', function () {
    $stage = stage();
    $stage->http->on(TICKETS, ['data' => ['is_known' => true, 'proved_by' => null, 'person_id' => 'person-1'], 'ticket' => 'secret-1'], 201);

    $ticket = $stage->people->tickets()->mint('person-1', ['ada@example.com'], 'Joining as an engineer.', 'Ada Lovelace');
    $asked = $stage->http->last(TICKETS);

    expect($ticket->secret)->toBe('secret-1')
        ->and($ticket->proof->isKnown)->toBeTrue()
        ->and($ticket->proof->personId)->toBe('person-1')
        ->and($ticket->proof->isProved())->toBeFalse()
        ->and($asked?->getHeaderLine('Authorization'))->toBe('Bearer '.API_KEY)
        ->and($asked?->getHeaderLine('Content-Type'))->toBe('application/json')
        ->and(json_decode((string) $asked?->getBody(), true))->toBe([
            'person_id' => 'person-1',
            'contacts' => ['ada@example.com'],
            'purpose' => 'Joining as an engineer.',
            'name' => 'Ada Lovelace',
        ]);
});

it('leaves out what it was not told, and names ten contacts at most', function () {
    $stage = stage();
    $stage->http->on(TICKETS, ['data' => [], 'ticket' => 'secret-1'], 201);

    $stage->people->tickets()->mint(contacts: array_map(fn (int $n): string => "person{$n}@example.com", range(1, 12)));

    /** @var array{person_id?: string, contacts: list<string>} $sent */
    $sent = json_decode((string) $stage->http->last(TICKETS)?->getBody(), true);

    expect($sent)->not->toHaveKey('person_id')
        ->and($sent['contacts'])->toHaveCount(10);
});

it('says so when People mints a ticket and does not say which', function () {
    $stage = stage();
    $stage->http->on(TICKETS, ['data' => []], 201);

    $stage->people->tickets()->mint();
})->throws(PeopleUnreachable::class, 'People minted a ticket and did not say what it is.');

it('reads back who proved a ticket', function () {
    $stage = stage();
    $stage->http->on(TICKETS.'/secret%201', ['data' => ['is_known' => true, 'proved_by' => 'user-1', 'person_id' => 'person-1']]);

    $proof = $stage->people->tickets()->proof('secret 1');

    expect($proof?->provedBy)->toBe('user-1')
        ->and($proof?->isProved())->toBeTrue()
        ->and($stage->http->last(TICKETS.'/secret%201')?->getHeaderLine('Authorization'))->toBe('Bearer '.API_KEY);
});

it('answers nothing for a ticket that has run out', function () {
    $stage = stage();
    $stage->http->on(TICKETS.'/secret-1', ['message' => 'That ticket has run out.'], 404);

    expect($stage->people->tickets()->proof('secret-1'))->toBeNull();
});

it('refuses to go on when People will not take this service\'s key', function () {
    $stage = stage();
    $stage->http->on(TICKETS.'/secret-1', ['message' => 'Unauthenticated.'], 401);

    $stage->people->tickets()->proof('secret-1');
})->throws(PeopleRefused::class, 'Unauthenticated.');

it('refuses to mint when the key may not', function () {
    $stage = stage();
    $stage->http->on(TICKETS, ['message' => 'This action is unauthorized.'], 403);

    $stage->people->tickets()->mint();
})->throws(PeopleRefused::class, 'This action is unauthorized.');

it('says so when People cannot be reached about a ticket', function () {
    $stage = stage();
    $stage->http->down(TICKETS.'/secret-1');

    $stage->people->tickets()->proof('secret-1');
})->throws(PeopleUnreachable::class);

it('says so when People answers a mint with nonsense', function () {
    $stage = stage();
    $stage->http->on(TICKETS, fn () => FakePeople::json([], 503));

    $stage->people->tickets()->mint();
})->throws(PeopleUnreachable::class);

it('will not mint without the key People issued to this service', function () {
    $clock = new FrozenClock;
    $http = new FakePeople;
    $stage = new Stage($http, $clock, new ArrayCache($clock), new Config(issuer: ISSUER, clientId: CLIENT));

    expect(fn () => $stage->people->tickets()->mint())
        ->toThrow(InvalidArgumentException::class, 'This needs the API key People issued to this service.')
        ->and($http->sent)->toBe([]);
});
