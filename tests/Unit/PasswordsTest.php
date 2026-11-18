<?php

declare(strict_types=1);

use Oki\People\Exceptions\PeopleRefused;
use Oki\People\Exceptions\PeopleUnreachable;

const CODE = '/v1/my/password/code';
const ACCOUNT_PASSWORD = '/v1/manage/users/user-2/password';

beforeEach(function () {
    $this->stage = stage();
    $this->passwords = $this->stage->people->caller('access-1')->passwords();
});

it('sends the caller a one-time code', function () {
    $this->stage->http->on(CODE, ['message' => 'We have sent a code to your email.'], 202);

    $this->passwords->sendCode();
    $asked = $this->stage->http->last(CODE);

    expect($asked?->getMethod())->toBe('POST')
        ->and($asked?->getHeaderLine('Authorization'))->toBe('Bearer access-1')
        ->and((string) $asked?->getBody())->toBe('{}');
});

it('says so when the account has nowhere to send a code', function () {
    $this->stage->http->on(CODE, ['message' => 'This account has no email address, so we cannot send a code.'], 422);

    $this->passwords->sendCode();
})->throws(PeopleRefused::class, 'This account has no email address, so we cannot send a code.');

it('says so when People cannot be reached to send a code', function () {
    $this->stage->http->down(CODE);

    $this->passwords->sendCode();
})->throws(PeopleUnreachable::class);

it('sets a password and says what it is', function () {
    $this->stage->http->on(ACCOUNT_PASSWORD, ['data' => ['password' => 'chosen-one', 'must_change' => false, 'signed_out_everywhere' => true]]);

    $set = $this->passwords->set('user-2', 'code-1', 'chosen-one', force: false, signOutEverywhere: true);
    $asked = $this->stage->http->last(ACCOUNT_PASSWORD);

    expect($set->password)->toBe('chosen-one')
        ->and($set->mustChange)->toBeFalse()
        ->and($set->signedOutEverywhere)->toBeTrue()
        ->and($asked?->getMethod())->toBe('PUT')
        ->and($asked?->getHeaderLine('Authorization'))->toBe('Bearer access-1')
        ->and(json_decode((string) $asked?->getBody(), true))
        ->toBe(['code' => 'code-1', 'password' => 'chosen-one', 'force' => false, 'sign_out_everywhere' => true]);
});

it('lets People generate one, which must then be changed', function () {
    $this->stage->http->on(ACCOUNT_PASSWORD, ['data' => ['password' => 'generated-one']]);

    $set = $this->passwords->set('user-2', 'code-1');

    expect($set->password)->toBe('generated-one')
        ->and($set->mustChange)->toBeTrue()
        ->and($set->signedOutEverywhere)->toBeFalse()
        ->and(json_decode((string) $this->stage->http->last(ACCOUNT_PASSWORD)?->getBody(), true))
        ->toBe(['code' => 'code-1', 'force' => true, 'sign_out_everywhere' => false]);
});

it('refuses to set a password on a spent code', function () {
    $this->stage->http->on(ACCOUNT_PASSWORD, ['message' => 'That code has been used.'], 422);

    $this->passwords->set('user-2', 'code-1');
})->throws(PeopleRefused::class, 'That code has been used.');

it('says so when People sets a password and does not say what it is', function () {
    $this->stage->http->on(ACCOUNT_PASSWORD, ['data' => ['must_change' => true]]);

    $this->passwords->set('user-2', 'code-1');
})->throws(PeopleUnreachable::class, 'People set a password and did not say what it is.');

it('says so when People cannot be reached to set a password', function () {
    $this->stage->http->down(ACCOUNT_PASSWORD);

    $this->passwords->set('user-2', 'code-1');
})->throws(PeopleUnreachable::class);
