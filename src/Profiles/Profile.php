<?php

declare(strict_types=1);

namespace Oki\People\Profiles;

use DateTimeImmutable;
use Oki\People\Exceptions\PeopleUnreachable;
use Oki\People\Register\Person;
use Oki\People\Support\Payload;

/**
 * What somebody shows the world under their handle.
 *
 * Public: People answers this to anybody, so it carries nothing private.
 */
final readonly class Profile
{
    public function __construct(
        public string $id,
        public ?string $username = null,
        public ?string $name = null,
        public ?Person $person = null,
        public ?DateTimeImmutable $joinedAt = null,
        public bool $isOnline = false,
        public ?DateTimeImmutable $lastSeenAt = null,
    ) {}

    /**
     * @param  array<string, mixed>  $row
     *
     * @throws PeopleUnreachable when People answered with something that is not a profile.
     */
    public static function from(array $row): self
    {
        $id = Payload::text($row, 'id');

        if ($id === null) {
            throw new PeopleUnreachable('People answered with no profile.');
        }

        $person = $row['person'] ?? null;

        return new self(
            id: $id,
            username: Payload::text($row, 'username'),
            name: Payload::text($row, 'name'),
            person: is_array($person) ? Person::from(Payload::data(Payload::named($person))) : null,
            joinedAt: Payload::moment($row, 'joined_at'),
            isOnline: Payload::flag($row, 'is_online'),
            lastSeenAt: Payload::moment($row, 'last_seen_at'),
        );
    }
}
