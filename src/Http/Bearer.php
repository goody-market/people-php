<?php

declare(strict_types=1);

namespace Oki\People\Http;

/**
 * What a call to People is made on.
 *
 * Two kinds, and they are never the same thing: a key issued to this service,
 * which acts as the service, and a person's access token, which acts as them.
 * They are made apart here so that a capability holding one cannot reach for
 * the other.
 */
final readonly class Bearer
{
    private function __construct(private string $credential) {}

    /** The key People issued to this service. */
    public static function system(string $apiKey): self
    {
        return new self($apiKey);
    }

    /** The access token the person calling this service presented. */
    public static function person(string $accessToken): self
    {
        return new self($accessToken);
    }

    /** @return array<string, string> */
    public function headers(): array
    {
        return ['Authorization' => 'Bearer '.$this->credential];
    }
}
