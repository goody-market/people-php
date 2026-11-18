<?php

declare(strict_types=1);

namespace Oki\People\Accounts;

use Oki\People\Exceptions\PeopleRefused;
use Oki\People\Exceptions\PeopleUnreachable;
use Oki\People\Http\Bearer;
use Oki\People\Http\Transport;
use Oki\People\Support\Payload;

/**
 * Setting a password, on the caller's own token.
 *
 * Both calls are about the caller even when the password is somebody else's:
 * the code is sent to them, and People checks it against them before it will
 * set anybody's password. An administrator proves it is them, then acts.
 */
final readonly class Passwords
{
    public function __construct(
        private string $issuer,
        private Transport $http,
        private Bearer $caller,
    ) {}

    /**
     * Send the caller a one-time code, to their own email address.
     *
     * @throws PeopleRefused when the account has no email address to send to.
     * @throws PeopleUnreachable
     */
    public function sendCode(): void
    {
        $this->http->json('POST', $this->issuer.'/v1/my/password/code', [], $this->caller->headers());
    }

    /**
     * Set an account's password, and say what it is.
     *
     * Leave the password out and People generates one. `force` leaves it
     * usable once, until they choose their own.
     *
     * @param  string  $account  The account id — People's user id, never a handle.
     * @param  string  $code  The one-time code `sendCode()` sent the caller.
     *
     * @throws PeopleRefused when the code is spent, or the caller may not set that password.
     * @throws PeopleUnreachable
     */
    public function set(string $account, string $code, ?string $password = null, bool $force = true, bool $signOutEverywhere = false): IssuedPassword
    {
        $body = $this->http->json(
            'PUT',
            $this->issuer.'/v1/manage/users/'.rawurlencode($account).'/password',
            array_filter([
                'code' => $code,
                'password' => $password,
                'force' => $force,
                'sign_out_everywhere' => $signOutEverywhere,
            ], fn (string|bool|null $field): bool => $field !== null),
            $this->caller->headers(),
        );

        return IssuedPassword::from(Payload::data($body), $force);
    }
}
