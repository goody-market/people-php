<?php

declare(strict_types=1);

namespace Oki\People;

use Oki\People\Accounts\Account;
use Oki\People\Accounts\MyProfile;
use Oki\People\Accounts\Passwords;
use Oki\People\Contacts\Contacts;
use Oki\People\Exceptions\PeopleRefused;
use Oki\People\Exceptions\PeopleUnreachable;
use Oki\People\Http\Bearer;
use Oki\People\Http\Transport;
use Oki\People\Register\Register;
use Oki\People\Support\Payload;

/**
 * Everything done as the person whose access token this is.
 *
 * People allows each call exactly what it allows them, so this service never
 * has to decide: it hands their token on and People answers as it would to
 * them. The key People issued to this service is deliberately out of reach
 * here — a service acting as itself and a service acting as somebody are
 * different calls, and must stay so.
 */
final readonly class Caller
{
    private Bearer $bearer;

    public function __construct(
        private string $issuer,
        private Transport $http,
        string $accessToken,
    ) {
        $this->bearer = Bearer::person($accessToken);
    }

    /**
     * Who this token is — People's own answer, not a local copy of one.
     *
     * @throws PeopleRefused when the token is no longer good.
     * @throws PeopleUnreachable
     */
    public function me(): Account
    {
        return Account::from(Payload::data($this->http->get($this->issuer.'/v1/me', $this->bearer->headers())));
    }

    /** Looking people up, writing them down, and folding duplicates. */
    public function people(): Register
    {
        return new Register($this->issuer, $this->http, $this->bearer);
    }

    /** The contacts they keep, their own and their people's. */
    public function contacts(): Contacts
    {
        return new Contacts($this->issuer, $this->http, $this->bearer);
    }

    /** Their own name, handle and primary contacts. */
    public function profile(): MyProfile
    {
        return new MyProfile($this->issuer, $this->http, $this->bearer);
    }

    /** One-time codes, and setting a password. */
    public function passwords(): Passwords
    {
        return new Passwords($this->issuer, $this->http, $this->bearer);
    }
}
