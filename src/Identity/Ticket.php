<?php

declare(strict_types=1);

namespace Oki\People\Identity;

use Oki\People\Exceptions\PeopleUnreachable;
use Oki\People\Support\Payload;

/**
 * An invitation ticket, just minted.
 *
 * `secret` is shown once, by People, to this call. Send it to whoever is
 * being invited and keep nothing but the fact that you did: anybody holding
 * it can prove it.
 */
final readonly class Ticket
{
    public function __construct(
        public string $secret,
        public TicketProof $proof = new TicketProof,
    ) {}

    /**
     * @param  array<mixed>  $body
     *
     * @throws PeopleUnreachable when People minted a ticket and did not say which.
     */
    public static function from(array $body): self
    {
        $secret = Payload::text(Payload::named($body), 'ticket');

        if ($secret === null) {
            throw new PeopleUnreachable('People minted a ticket and did not say what it is.');
        }

        return new self($secret, TicketProof::from(Payload::data($body)));
    }
}
