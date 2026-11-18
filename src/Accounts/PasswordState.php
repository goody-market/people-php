<?php

declare(strict_types=1);

namespace Oki\People\Accounts;

use DateTimeImmutable;
use Oki\People\Support\Payload;

/**
 * What People knows about an account's password.
 *
 * `chosen` false means nobody picked it: it was generated, and only whoever
 * chose a password is ever asked for their current one.
 */
final readonly class PasswordState
{
    public function __construct(
        public bool $chosen = false,
        public ?DateTimeImmutable $setAt = null,
        public bool $mustChange = false,
    ) {}

    /**
     * @param  array<string, mixed>  $row
     */
    public static function from(array $row): self
    {
        $password = $row['password'] ?? null;
        $named = is_array($password) ? Payload::named($password) : [];

        return new self(
            chosen: Payload::flag($named, 'chosen'),
            setAt: Payload::moment($named, 'set_at'),
            mustChange: Payload::flag($named, 'must_change'),
        );
    }
}
