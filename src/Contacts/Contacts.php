<?php

declare(strict_types=1);

namespace Oki\People\Contacts;

use Oki\People\Exceptions\PeopleRefused;
use Oki\People\Exceptions\PeopleUnreachable;
use Oki\People\Http\Bearer;
use Oki\People\Http\Transport;
use Oki\People\Support\Payload;

/**
 * The contacts the caller keeps — their own, and those of the people they manage.
 *
 * On their access token. People decides whose record may be written to; a
 * person this caller does not manage is refused.
 */
final readonly class Contacts
{
    public function __construct(
        private string $issuer,
        private Transport $http,
        private Bearer $caller,
    ) {}

    /**
     * All of them, narrowed by who they belong to.
     *
     * @param  string|null  $search  Matches the owner's name, never the contact itself, which People keeps encrypted.
     * @return list<Contact>
     *
     * @throws PeopleRefused|PeopleUnreachable
     */
    public function all(?string $search = null): array
    {
        $query = $search === null ? '' : '?'.http_build_query(['search' => $search]);

        return Contact::all(Payload::rows($this->http->get($this->at().$query, $this->caller->headers())));
    }

    /**
     * File one against a record.
     *
     * @throws PeopleRefused when the caller does not manage that record, or People already holds the contact.
     * @throws PeopleUnreachable
     */
    public function file(string $personId, string $type, string $value, ?string $tag = null, ?bool $isPrimary = null): Contact
    {
        $body = $this->http->json('POST', $this->at(), array_filter([
            'person_id' => $personId,
            'type' => $type,
            'value' => $value,
            'tag' => $tag,
            'is_primary' => $isPrimary,
        ], fn (string|bool|null $field): bool => $field !== null), $this->caller->headers());

        return $this->read($body);
    }

    /**
     * Change one. What it is — an email address, a phone number — cannot change.
     *
     * @throws PeopleRefused|PeopleUnreachable
     */
    public function change(string $contact, ?string $value = null, ?string $tag = null, ?bool $isPrimary = null): Contact
    {
        $body = $this->http->json('PATCH', $this->at().'/'.rawurlencode($contact), array_filter([
            'value' => $value,
            'tag' => $tag,
            'is_primary' => $isPrimary,
        ], fn (string|bool|null $field): bool => $field !== null), $this->caller->headers());

        return $this->read($body);
    }

    /**
     * Drop one.
     *
     * @throws PeopleRefused|PeopleUnreachable
     */
    public function drop(string $contact): void
    {
        $this->http->json('DELETE', $this->at().'/'.rawurlencode($contact), [], $this->caller->headers());
    }

    /**
     * @param  array<mixed>  $body
     *
     * @throws PeopleUnreachable
     */
    private function read(array $body): Contact
    {
        return Contact::from(Payload::data($body)) ?? throw new PeopleUnreachable('People answered with no contact.');
    }

    private function at(): string
    {
        return $this->issuer.'/v1/my/contacts';
    }
}
