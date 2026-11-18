<?php

declare(strict_types=1);

namespace Oki\People\Accounts;

use DateTimeImmutable;
use Oki\People\Contacts\Contact;
use Oki\People\Exceptions\PeopleUnreachable;
use Oki\People\Register\Person;
use Oki\People\Support\Payload;

/**
 * An account at People, as People describes it to whoever holds its token.
 *
 * `roles` and `permissions` are People's own, about People. They say nothing
 * about what this person may do here — decide that from your own records.
 */
final readonly class Account
{
    /**
     * @param  list<Contact>  $contacts
     * @param  list<string>  $roles
     * @param  list<string>  $permissions
     * @param  array<string, mixed>|null  $address  Present only where the call read it.
     */
    public function __construct(
        public string $id,
        public ?string $username = null,
        public bool $isActive = false,
        public ?DateTimeImmutable $emailVerifiedAt = null,
        public PasswordState $password = new PasswordState,
        public array $contacts = [],
        public ?Person $person = null,
        public array $roles = [],
        public array $permissions = [],
        public ?array $address = null,
    ) {}

    /**
     * @param  array<string, mixed>  $row
     *
     * @throws PeopleUnreachable when People answered with something that is not an account.
     */
    public static function from(array $row): self
    {
        $id = Payload::text($row, 'id');

        if ($id === null) {
            throw new PeopleUnreachable('People answered with no account.');
        }

        $person = $row['person'] ?? null;
        $address = $row['address'] ?? null;

        return new self(
            id: $id,
            username: Payload::text($row, 'username'),
            isActive: Payload::flag($row, 'is_active', true),
            emailVerifiedAt: Payload::moment($row, 'email_verified_at'),
            password: PasswordState::from($row),
            contacts: Contact::all(Payload::nested($row, 'contacts')),
            person: is_array($person) ? Person::from(Payload::data(Payload::named($person))) : null,
            roles: Payload::words($row, 'roles'),
            permissions: Payload::words($row, 'permissions'),
            address: is_array($address) ? Payload::data(Payload::named($address)) : null,
        );
    }
}
