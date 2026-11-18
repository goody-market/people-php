<?php

declare(strict_types=1);

namespace Oki\People\Accounts;

use Oki\People\Exceptions\PeopleRefused;
use Oki\People\Exceptions\PeopleUnreachable;
use Oki\People\Http\Bearer;
use Oki\People\Http\Transport;
use Oki\People\Support\Payload;

/**
 * The caller's own profile at People.
 *
 * Their name, their handle and their primary contacts, changed on their own
 * token. Nothing here can reach anybody else's.
 */
final readonly class MyProfile
{
    public function __construct(
        private string $issuer,
        private Transport $http,
        private Bearer $caller,
    ) {}

    /**
     * Change what they gave you to change, and nothing else.
     *
     * @param  array<string, mixed>  $details  Any of `first_name`, `other_name`, `last_name`, `gender`, `date_of_birth`, `username`, `phone`, `phone_tag`, `email`, `email_tag`.
     *
     * @throws PeopleRefused when People will not take the details — a handle somebody else holds, a contact already filed.
     * @throws PeopleUnreachable
     */
    public function change(array $details): Account
    {
        return Account::from(Payload::data(
            $this->http->json('PATCH', $this->issuer.'/v1/my/profile', $details, $this->caller->headers()),
        ));
    }
}
