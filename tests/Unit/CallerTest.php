<?php

declare(strict_types=1);

use Oki\People\Accounts\Account;
use Oki\People\Exceptions\PeopleRefused;
use Oki\People\Exceptions\PeopleUnreachable;

/** @return array<string, mixed> */
function account_row(): array
{
    return [
        'id' => 'user-1',
        'username' => 'ada',
        'is_active' => true,
        'email_verified_at' => '2026-01-04T09:00:00+00:00',
        'password' => ['chosen' => true, 'set_at' => '2026-02-01T09:00:00+00:00', 'must_change' => false],
        'contacts' => [['id' => 'contact-1', 'type' => 'email', 'value' => 'ada@example.com']],
        'person' => ['id' => 'person-1', 'name' => 'Ada Lovelace'],
        'address' => ['line' => '1 Analytical Way', 'city' => 'London'],
        'roles' => ['member', 7],
        'permissions' => ['people.manage.all'],
    ];
}

beforeEach(function () {
    $this->stage = stage();
    $this->caller = $this->stage->people->caller('access-1');
});

it('asks People who a token is', function () {
    $this->stage->http->on('/v1/me', ['data' => account_row()]);

    $account = $this->caller->me();

    expect($account->id)->toBe('user-1')
        ->and($account->username)->toBe('ada')
        ->and($account->isActive)->toBeTrue()
        ->and($account->emailVerifiedAt?->format('c'))->toBe('2026-01-04T09:00:00+00:00')
        ->and($account->password->chosen)->toBeTrue()
        ->and($account->password->setAt?->format('c'))->toBe('2026-02-01T09:00:00+00:00')
        ->and($account->password->mustChange)->toBeFalse()
        ->and($account->contacts)->toHaveCount(1)
        ->and($account->person?->id)->toBe('person-1')
        ->and($account->address)->toBe(['line' => '1 Analytical Way', 'city' => 'London'])
        ->and($account->roles)->toBe(['member'])
        ->and($account->permissions)->toBe(['people.manage.all'])
        ->and($this->stage->http->last('/v1/me')?->getHeaderLine('Authorization'))->toBe('Bearer access-1');
});

it('reads an account People said little about', function () {
    $this->stage->http->on('/v1/me', ['data' => ['id' => 'user-1']]);

    $account = $this->caller->me();

    expect($account->isActive)->toBeTrue()
        ->and($account->password->chosen)->toBeFalse()
        ->and($account->password->setAt)->toBeNull()
        ->and($account->contacts)->toBe([])
        ->and($account->person)->toBeNull()
        ->and($account->address)->toBeNull()
        ->and($account->roles)->toBe([]);
});

it('says so when the token is no longer good', function () {
    $this->stage->http->on('/v1/me', ['message' => 'Unauthenticated.'], 401);

    $this->caller->me();
})->throws(PeopleRefused::class, 'Unauthenticated.');

it('says so when People answers with no account', function () {
    $this->stage->http->on('/v1/me', ['data' => ['username' => 'ada']]);

    $this->caller->me();
})->throws(PeopleUnreachable::class, 'People answered with no account.');

it('says so when People cannot be reached about who a token is', function () {
    $this->stage->http->down('/v1/me');

    $this->caller->me();
})->throws(PeopleUnreachable::class);

it('changes the caller\'s own profile', function () {
    $this->stage->http->on('/v1/my/profile', ['data' => account_row()]);

    $account = $this->caller->profile()->change(['first_name' => 'Ada', 'username' => 'ada']);
    $asked = $this->stage->http->last('/v1/my/profile');

    expect($account->username)->toBe('ada')
        ->and($asked?->getMethod())->toBe('PATCH')
        ->and($asked?->getHeaderLine('Authorization'))->toBe('Bearer access-1')
        ->and(json_decode((string) $asked?->getBody(), true))->toBe(['first_name' => 'Ada', 'username' => 'ada']);
});

it('refuses a profile change People will not take', function () {
    $this->stage->http->on('/v1/my/profile', ['message' => 'That handle is taken.'], 422);

    $this->caller->profile()->change(['username' => 'ada']);
})->throws(PeopleRefused::class, 'That handle is taken.');

it('says so when People cannot be reached to change a profile', function () {
    $this->stage->http->down('/v1/my/profile');

    $this->caller->profile()->change(['username' => 'ada']);
})->throws(PeopleUnreachable::class);

it('reads an account whose password People described as nothing', function () {
    expect(Account::from(['id' => 'user-1', 'password' => 'nonsense', 'person' => 'nonsense', 'address' => 'nonsense'])->password->chosen)->toBeFalse();
});
