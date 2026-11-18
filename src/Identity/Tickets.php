<?php

declare(strict_types=1);

namespace Oki\People\Identity;

use Oki\People\Config;
use Oki\People\Exceptions\PeopleRefused;
use Oki\People\Exceptions\PeopleUnreachable;
use Oki\People\Http\Bearer;
use Oki\People\Http\Transport;
use Oki\People\Support\Payload;

/**
 * Invitations, on the key People issued to this service.
 *
 * A ticket is a one-time secret that stands for "whoever we are inviting".
 * Send it to them; when they sign in with it, People says who proved it and
 * which record it names, and this service learns their account id without
 * ever having been told their password.
 *
 * Nothing here acts as a person. The key acts as this service, which is why
 * a person's access token cannot be passed to any of it.
 */
final readonly class Tickets
{
    public function __construct(
        private Config $config,
        private Transport $http,
    ) {}

    /**
     * Mint one.
     *
     * @param  string|null  $personId  The record being invited, where this service already knows it.
     * @param  list<string>  $contacts  Up to ten ways of reaching them; People tells you whether it knows any of them.
     * @param  string|null  $purpose  What the invitation is for, shown to them.
     * @param  string|null  $name  What to call them, where this service knows.
     *
     * @throws PeopleRefused when People will not take this service's key.
     * @throws PeopleUnreachable
     */
    public function mint(?string $personId = null, array $contacts = [], ?string $purpose = null, ?string $name = null): Ticket
    {
        return Ticket::from($this->http->json('POST', $this->at(), array_filter([
            'person_id' => $personId,
            'contacts' => array_slice($contacts, 0, 10),
            'purpose' => $purpose,
            'name' => $name,
        ], fn (string|array|null $value): bool => $value !== null), Bearer::system($this->config->apiKey())->headers()));
    }

    /**
     * Who proved one — null for a ticket People has never heard of, or that has run out.
     *
     * Null is an answer, not a failure: a ticket lapses, and People forgets
     * it. A refusal of this service's key still throws.
     *
     * @throws PeopleRefused|PeopleUnreachable
     */
    public function proof(string $secret): ?TicketProof
    {
        try {
            $body = $this->http->get($this->at().'/'.rawurlencode($secret), Bearer::system($this->config->apiKey())->headers());
        } catch (PeopleRefused $refused) {
            if ($refused->status === 404) {
                return null;
            }

            throw $refused;
        }

        return TicketProof::from(Payload::data($body));
    }

    private function at(): string
    {
        return $this->config->issuer().'/v1/manage/identity/tickets';
    }
}
