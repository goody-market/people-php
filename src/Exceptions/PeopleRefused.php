<?php

declare(strict_types=1);

namespace Oki\People\Exceptions;

/**
 * People answered, and the answer was no.
 *
 * `$error` is the OAuth error code where there is one — `invalid_grant`,
 * `invalid_client` — and `$status` the HTTP status. A validation refusal also
 * names what was wrong field by field, see `errors()`.
 */
final class PeopleRefused extends PeopleException
{
    public function __construct(
        string $message,
        public readonly int $status,
        public readonly ?string $error = null,
        /** @var array<string, list<string>> */
        private readonly array $errors = [],
    ) {
        parent::__construct($message, $status);
    }

    /**
     * What People said was wrong, field => messages; empty when it named none.
     *
     * @return array<string, list<string>>
     */
    public function errors(): array
    {
        return $this->errors;
    }

    /**
     * One sentence per field, for a report a person corrects their input from.
     *
     * @return list<string>
     */
    public function sentences(): array
    {
        return array_values(array_map(fn (array $messages): string => implode(' ', $messages), $this->errors));
    }
}
