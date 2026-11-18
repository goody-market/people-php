<?php

declare(strict_types=1);

namespace Oki\People\Profiles;

use Oki\People\Exceptions\PeopleRefused;
use Oki\People\Exceptions\PeopleUnreachable;
use Oki\People\Http\Transport;
use Oki\People\Support\Payload;

/**
 * Somebody's public profile, read by their handle.
 *
 * On no credential at all: People answers this to anybody, and what it
 * answers is what that person chose to show.
 */
final readonly class Profiles
{
    public function __construct(
        private string $issuer,
        private Transport $http,
    ) {}

    /**
     * The profile under a handle — null where nobody holds it.
     *
     * @throws PeopleRefused|PeopleUnreachable
     */
    public function of(string $username): ?Profile
    {
        try {
            $body = $this->http->get($this->issuer.'/v1/profiles/'.rawurlencode($username));
        } catch (PeopleRefused $refused) {
            if ($refused->status === 404) {
                return null;
            }

            throw $refused;
        }

        return Profile::from(Payload::data($body));
    }
}
