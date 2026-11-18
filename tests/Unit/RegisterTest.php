<?php

declare(strict_types=1);

use Oki\People\Exceptions\PeopleRefused;
use Oki\People\Exceptions\PeopleUnreachable;
use Oki\People\Register\Person;
use Oki\People\Register\Records;
use Oki\People\Tests\Support\FakePeople;

const LOOKUP = '/v1/people/lookup';

/** @return array<string, mixed> */
function person_row(string $id = 'person-1'): array
{
    return [
        'id' => $id,
        'user_id' => 'user-1',
        'first_name' => 'Ada',
        'other_name' => 'Augusta',
        'last_name' => 'Lovelace',
        'name' => 'Ada Lovelace',
        'initials' => 'AL',
        'username' => 'ada',
        'gender' => 'female',
        'marital_status' => 'single',
        'date_of_birth' => '1815-12-10',
        'nationality' => 'British',
        'citizenship' => 'British',
        'avatar_url' => 'https://people.example.com/ada.png',
        'trashed' => false,
        'created_at' => '2026-01-04T09:00:00+00:00',
        'contacts' => ['data' => [['id' => 'contact-1', 'type' => 'email', 'value' => 'ada@example.com', 'is_primary' => true]]],
    ];
}

beforeEach(function () {
    $this->stage = stage();
    $this->caller = $this->stage->people->caller('access-1');
});

it('writes somebody down on the caller\'s own token', function () {
    $this->stage->http->on('/v1/people', ['data' => person_row()], 201);

    $person = $this->caller->people()->record(['first_name' => 'Ada', 'contacts' => [['type' => 'email', 'value' => 'ada@example.com']]]);
    $asked = $this->stage->http->last('/v1/people');

    expect($person->id)->toBe('person-1')
        ->and($person->userId)->toBe('user-1')
        ->and($person->name)->toBe('Ada Lovelace')
        ->and($person->initials)->toBe('AL')
        ->and($person->maritalStatus)->toBe('single')
        ->and($person->dateOfBirth?->format('Y-m-d'))->toBe('1815-12-10')
        ->and($person->citizenship)->toBe('British')
        ->and($person->avatarUrl)->toBe('https://people.example.com/ada.png')
        ->and($person->trashed)->toBeFalse()
        ->and($person->createdAt?->format('c'))->toBe('2026-01-04T09:00:00+00:00')
        ->and($person->contacts)->toHaveCount(1)
        ->and($person->contacts[0]->value)->toBe('ada@example.com')
        ->and($asked?->getHeaderLine('Authorization'))->toBe('Bearer access-1')
        ->and(json_decode((string) $asked?->getBody(), true))->toBe(['first_name' => 'Ada', 'contacts' => [['type' => 'email', 'value' => 'ada@example.com']]]);
});

it('says so when People writes somebody down and does not say who', function () {
    $this->stage->http->on('/v1/people', ['data' => ['name' => 'Ada Lovelace']], 201);

    $this->caller->people()->record([]);
})->throws(PeopleUnreachable::class, 'People wrote somebody down and did not say who.');

it('refuses to write down details People will not take', function () {
    $this->stage->http->on('/v1/people', ['message' => 'That contact belongs to somebody else.'], 422);

    $this->caller->people()->record([]);
})->throws(PeopleRefused::class, 'That contact belongs to somebody else.');

it('says so when People cannot be reached to write somebody down', function () {
    $this->stage->http->down('/v1/people');

    $this->caller->people()->record([]);
})->throws(PeopleUnreachable::class);

it('finds everybody holding a contact', function () {
    $this->stage->http->on(LOOKUP, ['data' => [person_row(), person_row('person-2')]]);

    $found = $this->caller->people()->whoHolds('ada@example.com');

    expect($found)->toHaveCount(2)
        ->and($found->find('person-2')?->id)->toBe('person-2')
        ->and($found->find('person-9'))->toBeNull()
        ->and($this->stage->http->last(LOOKUP)?->getUri()->getQuery())->toBe('contact=ada%40example.com');
});

it('answers with nobody where nobody holds the contact', function () {
    $this->stage->http->on(LOOKUP, ['message' => 'Nobody is recorded under that contact.'], 404);

    expect($this->caller->people()->whoHolds('nobody@example.com'))->toHaveCount(0);
});

it('reads a page of people, keyed by the id asked about', function () {
    $this->stage->http->on(LOOKUP, ['data' => [person_row(), person_row('person-2')]]);

    $found = $this->caller->people()->all(['person-1', 'person-2', 'person-1', '']);

    expect(array_keys($found->people))->toBe(['person-1', 'person-2'])
        ->and($found->all())->toHaveCount(2)
        ->and(iterator_to_array($found))->toHaveKey('person-1')
        ->and($this->stage->http->last(LOOKUP)?->getUri()->getQuery())->toBe('ids%5B0%5D=person-1&ids%5B1%5D=person-2');
});

it('asks nothing when there is nobody to ask about', function () {
    expect($this->caller->people()->all([])->people)->toBe([])
        ->and($this->stage->http->asked(LOOKUP))->toBe(0);
});

it('passes over a row that is not a person', function () {
    $this->stage->http->on(LOOKUP, ['data' => ['nonsense', ['name' => 'No id'], person_row()]]);

    expect($this->caller->people()->all(['person-1']))->toHaveCount(1);
});

it('reads a single person answered without a list', function () {
    $this->stage->http->on(LOOKUP, ['data' => person_row()]);

    expect($this->caller->people()->whoHolds('ada@example.com')->find('person-1')?->username)->toBe('ada');
});

it('says so when a lookup is refused', function () {
    $this->stage->http->on(LOOKUP, ['message' => 'Unauthenticated.'], 401);

    $this->caller->people()->whoHolds('ada@example.com');
})->throws(PeopleRefused::class);

it('says so when People cannot be reached for a lookup', function () {
    $this->stage->http->down(LOOKUP);

    $this->caller->people()->all(['person-1']);
})->throws(PeopleUnreachable::class);

it('folds duplicates into the record that survived', function () {
    $this->stage->http->on('/v1/manage/people/duplicates/merge', ['data' => person_row()]);

    $survivor = $this->caller->people()->merge(['person-1', 'person-2']);

    expect($survivor?->id)->toBe('person-1')
        ->and(json_decode((string) $this->stage->http->last('/v1/manage/people/duplicates/merge')?->getBody(), true))
        ->toBe(['people' => ['person-1', 'person-2']]);
});

it('says nothing survived where People folded nothing', function () {
    $this->stage->http->on('/v1/manage/people/duplicates/merge', ['data' => null]);

    expect($this->caller->people()->merge(['person-1', 'person-2']))->toBeNull();
});

it('refuses to fold for somebody who may not', function () {
    $this->stage->http->on('/v1/manage/people/duplicates/merge', ['message' => 'This action is unauthorized.'], 403);

    $this->caller->people()->merge(['person-1', 'person-2']);
})->throws(PeopleRefused::class);

it('says so when People cannot be reached to fold', function () {
    $this->stage->http->down('/v1/manage/people/duplicates/merge');

    $this->caller->people()->merge(['person-1', 'person-2']);
})->throws(PeopleUnreachable::class);

it('reads a person whose account People names as a summary', function () {
    expect(Person::from(['id' => 'person-1', 'user' => ['id' => 'user-2']])?->userId)->toBe('user-2')
        ->and(Person::from(['id' => 'person-1', 'user' => 'nonsense'])?->userId)->toBeNull()
        ->and(Person::from(['name' => 'No id']))->toBeNull()
        ->and((new Records)->count())->toBe(0);
});
