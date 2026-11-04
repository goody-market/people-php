<?php

declare(strict_types=1);

namespace Oki\People;

use Oki\People\Exceptions\PeopleRefused;
use Oki\People\Exceptions\PeopleUnreachable;
use Oki\People\Http\Transport;
use Oki\People\OAuth\ClientCredentials;

/**
 * Whether an access token is still good — RFC 7662.
 *
 * A token verifies offline against People's public key, which cannot know it
 * was revoked: its life is the window a stolen one is useful in. This asks
 * People, which reads the record the token was issued from.
 *
 * **A round trip, and a deliberate one.** It is for the operation a
 * signature is not enough for — approving a payment, changing where money
 * goes, acting as somebody else. Everything else reads a projection and does
 * not pay for this.
 *
 * Asked on this client's own token, so only a confidential client can ask,
 * and People answers only its own estate's applications.
 */
final readonly class Introspection
{
    private const array SCOPES = ['tokens.introspect'];

    public function __construct(
        private Discovery $discovery,
        private Transport $http,
        private ClientCredentials $credentials,
    ) {}

    /**
     * What People says about a token.
     *
     * Every way of being no good answers the same: unparseable, signed by a
     * key People never published, expired, revoked, or naming a record that
     * is gone. Read `active` and nothing else to decide.
     *
     * @throws PeopleRefused|PeopleUnreachable
     */
    public function of(string $token): IntrospectedToken
    {
        try {
            $answer = $this->ask($token);
        } catch (PeopleRefused $refused) {
            if ($refused->status !== 401) {
                throw $refused;
            }

            // The held token was revoked or lapsed early. One more try, on a new one.
            $this->credentials->forget(self::SCOPES);
            $answer = $this->ask($token);
        }

        return IntrospectedToken::from($answer);
    }

    /**
     * @return array<mixed>
     */
    private function ask(string $token): array
    {
        return $this->http->form(
            $this->discovery->endpoint('introspection_endpoint'),
            ['token' => $token],
            ['Authorization' => 'Bearer '.$this->credentials->token(self::SCOPES)],
        );
    }
}
