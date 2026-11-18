<?php

declare(strict_types=1);

namespace Oki\People\Register;

use DateTimeImmutable;
use Oki\People\Contacts\Contact;
use Oki\People\Support\Payload;

/**
 * Somebody as People records them.
 *
 * People answers with as much of this as the call it answers knows: a lookup
 * by id carries a summary, a record just written carries its contacts. A
 * field People did not name is null here, never guessed.
 */
final readonly class Person
{
    /**
     * @param  list<Contact>  $contacts
     */
    public function __construct(
        public string $id,
        public ?string $userId = null,
        public ?string $name = null,
        public ?string $firstName = null,
        public ?string $otherName = null,
        public ?string $lastName = null,
        public ?string $initials = null,
        public ?string $username = null,
        public ?string $gender = null,
        public ?string $maritalStatus = null,
        public ?DateTimeImmutable $dateOfBirth = null,
        public ?string $nationality = null,
        public ?string $citizenship = null,
        public ?string $avatarUrl = null,
        public array $contacts = [],
        public bool $trashed = false,
        public ?DateTimeImmutable $createdAt = null,
    ) {}

    /**
     * @param  array<string, mixed>  $row
     */
    public static function from(array $row): ?self
    {
        $id = Payload::text($row, 'id');

        if ($id === null) {
            return null;
        }

        $user = $row['user'] ?? null;

        return new self(
            id: $id,
            userId: Payload::text($row, 'user_id')
                ?? (is_array($user) ? Payload::text(Payload::named($user), 'id') : null),
            name: Payload::text($row, 'name'),
            firstName: Payload::text($row, 'first_name'),
            otherName: Payload::text($row, 'other_name'),
            lastName: Payload::text($row, 'last_name'),
            initials: Payload::text($row, 'initials'),
            username: Payload::text($row, 'username'),
            gender: Payload::text($row, 'gender'),
            maritalStatus: Payload::text($row, 'marital_status'),
            dateOfBirth: Payload::moment($row, 'date_of_birth'),
            nationality: Payload::text($row, 'nationality'),
            citizenship: Payload::text($row, 'citizenship'),
            avatarUrl: Payload::text($row, 'avatar_url'),
            contacts: Contact::all(Payload::nested($row, 'contacts')),
            trashed: Payload::flag($row, 'trashed'),
            createdAt: Payload::moment($row, 'created_at'),
        );
    }
}
