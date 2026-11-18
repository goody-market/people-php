<?php

declare(strict_types=1);

namespace Oki\People\Accounts;

use Oki\People\Exceptions\PeopleUnreachable;
use Oki\People\Support\Payload;

/**
 * A password People has just set, in plain text.
 *
 * People shows it once and never again. Hand it to whoever asked for it and
 * keep no copy: it is not yours to store, and `mustChange` says it is not
 * meant to outlive its first use.
 */
final readonly class IssuedPassword
{
    public function __construct(
        public string $password,
        public bool $mustChange = true,
        public bool $signedOutEverywhere = false,
    ) {}

    /**
     * @param  array<string, mixed>  $row
     *
     * @throws PeopleUnreachable when People set a password and did not say which.
     */
    public static function from(array $row, bool $force = true): self
    {
        $password = Payload::text($row, 'password');

        if ($password === null) {
            throw new PeopleUnreachable('People set a password and did not say what it is.');
        }

        return new self(
            password: $password,
            mustChange: Payload::flag($row, 'must_change', $force),
            signedOutEverywhere: Payload::flag($row, 'signed_out_everywhere'),
        );
    }
}
