<?php

declare(strict_types=1);

namespace Oki\People\Identity;

use Oki\People\Support\Payload;

/**
 * Who proved a ticket, if anybody has yet.
 *
 * `provedBy` is the account that signed in with it and `personId` the record
 * it names; both are null until somebody does. `isKnown` says People already
 * had somebody under one of the contacts the ticket was minted for.
 */
final readonly class TicketProof
{
    public function __construct(
        public bool $isKnown = false,
        public ?string $provedBy = null,
        public ?string $personId = null,
    ) {}

    /** Whether somebody has proved it. */
    public function isProved(): bool
    {
        return $this->provedBy !== null;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public static function from(array $row): self
    {
        return new self(
            isKnown: Payload::flag($row, 'is_known'),
            provedBy: Payload::text($row, 'proved_by'),
            personId: Payload::text($row, 'person_id'),
        );
    }
}
