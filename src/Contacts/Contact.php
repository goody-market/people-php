<?php

declare(strict_types=1);

namespace Oki\People\Contacts;

use DateTimeImmutable;
use Oki\People\Support\Payload;

/** One way of reaching somebody: an email address, a phone number. */
final readonly class Contact
{
    public function __construct(
        public string $id,
        public string $type,
        public string $value,
        public ?string $tag = null,
        public bool $isPrimary = false,
        public bool $isVerified = false,
        public ?DateTimeImmutable $createdAt = null,
    ) {}

    /**
     * @param  array<string, mixed>  $row
     */
    public static function from(array $row): ?self
    {
        $id = Payload::text($row, 'id');
        $type = Payload::text($row, 'type');
        $value = Payload::text($row, 'value');

        if ($id === null || $type === null || $value === null) {
            return null;
        }

        return new self(
            id: $id,
            type: $type,
            value: $value,
            tag: Payload::text($row, 'tag'),
            isPrimary: Payload::flag($row, 'is_primary'),
            isVerified: Payload::flag($row, 'is_verified'),
            createdAt: Payload::moment($row, 'created_at'),
        );
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<self>
     */
    public static function all(array $rows): array
    {
        $contacts = [];

        foreach ($rows as $row) {
            $contact = self::from($row);

            if ($contact !== null) {
                $contacts[] = $contact;
            }
        }

        return $contacts;
    }
}
