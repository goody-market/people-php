<?php

declare(strict_types=1);

use Oki\People\Support\Payload;

it('reads the resource an answer carries, wrapped or not', function () {
    expect(Payload::data(['data' => ['id' => 'person-1']]))->toBe(['id' => 'person-1'])
        ->and(Payload::data(['id' => 'person-1']))->toBe(['id' => 'person-1'])
        ->and(Payload::data(['data' => 'nonsense']))->toBe([]);
});

it('reads the resources an answer carries, however many', function () {
    expect(Payload::rows(['data' => [['id' => 'person-1'], 'nonsense']]))->toBe([['id' => 'person-1']])
        ->and(Payload::rows(['data' => ['id' => 'person-1']]))->toBe([['id' => 'person-1']])
        ->and(Payload::rows(['data' => 'nonsense']))->toBe([])
        ->and(Payload::nested(['contacts' => 'nonsense'], 'contacts'))->toBe([]);
});

it('reads a field only where there is one to read', function () {
    expect(Payload::text(['name' => ''], 'name'))->toBeNull()
        ->and(Payload::text(['name' => 7], 'name'))->toBeNull()
        ->and(Payload::flag(['is_primary' => 'yes'], 'is_primary', true))->toBeTrue()
        ->and(Payload::moment(['at' => 'never'], 'at'))->toBeNull()
        ->and(Payload::words(['roles' => 'member'], 'roles'))->toBe([]);
});
