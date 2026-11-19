<?php

declare(strict_types=1);

use Oki\People\Exceptions\PeopleRefused;

beforeEach(function () {
    $this->stage = stage();
    $this->caller = $this->stage->people->caller('access-1');
});

/** Asks People for a change it answers with $body at $status, and returns what it refused with. */
function refusal(object $test, array $body, int $status): PeopleRefused
{
    $test->stage->http->on('/v1/my/profile', $body, $status);

    try {
        $test->caller->profile()->change(['username' => 'ada']);
    } catch (PeopleRefused $refused) {
        return $refused;
    }

    throw new LogicException('People did not refuse.');
}

it('carries the field errors of a 422', function () {
    $refused = refusal($this, [
        'message' => 'The given data was invalid.',
        'errors' => [
            'username' => ['The username has already been taken.'],
            'contacts.0.value' => ['The email is invalid.', 'The email is too long.'],
        ],
    ], 422);

    expect($refused->status)->toBe(422)
        ->and($refused->getMessage())->toBe('The given data was invalid.')
        ->and($refused->errors())->toBe([
            'username' => ['The username has already been taken.'],
            'contacts.0.value' => ['The email is invalid.', 'The email is too long.'],
        ])
        ->and($refused->sentences())->toBe([
            'The username has already been taken.',
            'The email is invalid. The email is too long.',
        ]);
});

it('has no field errors when a refusal names none', function () {
    $refused = refusal($this, ['message' => 'Unauthenticated.'], 401);

    expect($refused->status)->toBe(401)
        ->and($refused->getMessage())->toBe('Unauthenticated.')
        ->and($refused->errors())->toBe([])
        ->and($refused->sentences())->toBe([]);
});

it('keeps what it can of malformed field errors', function () {
    $refused = refusal($this, [
        'message' => 'The given data was invalid.',
        'errors' => [
            'username' => 'Taken.',
            'handle' => ['Short.', 7, ''],
            'bad' => [7, null],
            'worse' => 5,
            3 => ['A list index is not a field.'],
        ],
    ], 422);

    expect($refused->errors())->toBe(['username' => ['Taken.'], 'handle' => ['Short.']]);
});

it('ignores field errors that are not a map', function (mixed $errors) {
    expect(refusal($this, ['message' => 'No.', 'errors' => $errors], 422)->errors())->toBe([]);
})->with(['a string' => 'nope', 'a number' => 4, 'null' => null]);

it('ignores field errors when the whole answer is not an object', function () {
    $this->stage->http->on('/v1/my/profile', ['x'], 422);
    $refused = null;

    try {
        $this->caller->profile()->change([]);
    } catch (PeopleRefused $caught) {
        $refused = $caught;
    }

    expect($refused?->errors())->toBe([])
        ->and($refused?->status)->toBe(422);
});

it('still builds from its original arguments', function () {
    $refused = new PeopleRefused('No.', 400, 'invalid_grant');

    expect($refused->errors())->toBe([])
        ->and($refused->error)->toBe('invalid_grant')
        ->and($refused->getCode())->toBe(400);
});
