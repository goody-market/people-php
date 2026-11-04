<?php

declare(strict_types=1);

namespace Oki\People;

use DateTimeImmutable;

/**
 * What People said about a token.
 *
 * `active` is the answer. Everything else is absent for a token that is not,
 * because People says no more about one than that.
 */
final readonly class IntrospectedToken
{
    /**
     * @param  list<string>  $scopes
     * @param  list<string>  $audiences
     */
    public function __construct(
        public bool $active,
        public array $scopes = [],
        public ?string $clientId = null,
        public ?string $subject = null,
        public ?string $username = null,
        public array $audiences = [],
        public ?string $issuer = null,
        public ?DateTimeImmutable $expiresAt = null,
        public ?string $id = null,
    ) {}

    /**
     * @param  array<mixed>  $answer
     */
    public static function from(array $answer): self
    {
        if (($answer['active'] ?? false) !== true) {
            return new self(active: false);
        }

        $scope = self::text($answer, 'scope');
        $audience = $answer['aud'] ?? null;
        $expires = $answer['exp'] ?? null;

        return new self(
            active: true,
            scopes: $scope === null ? [] : array_values(array_filter(explode(' ', $scope))),
            clientId: self::text($answer, 'client_id'),
            subject: self::text($answer, 'sub'),
            username: self::text($answer, 'username'),
            audiences: array_values(array_filter(is_array($audience) ? $audience : [$audience], is_string(...))),
            issuer: self::text($answer, 'iss'),
            expiresAt: is_int($expires) ? new DateTimeImmutable('@'.$expires) : null,
            id: self::text($answer, 'jti'),
        );
    }

    /** Whether this token was granted something. */
    public function can(string $scope): bool
    {
        return $this->active && in_array($scope, $this->scopes, true);
    }

    /** @param  array<mixed>  $answer */
    private static function text(array $answer, string $field): ?string
    {
        $value = $answer[$field] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }
}
