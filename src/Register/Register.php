<?php

declare(strict_types=1);

namespace Oki\People\Register;

use Oki\People\Exceptions\PeopleRefused;
use Oki\People\Exceptions\PeopleUnreachable;
use Oki\People\Http\Bearer;
use Oki\People\Http\Transport;
use Oki\People\Support\Payload;

/**
 * The record of who people are, read and written as the person calling.
 *
 * Everything here is done on their access token, so People allows exactly
 * what it allows them — a lookup anybody may make, a merge only somebody who
 * manages records may.
 */
final readonly class Register
{
    public function __construct(
        private string $issuer,
        private Transport $http,
        private Bearer $caller,
    ) {}

    /**
     * Write somebody down, or find them already written.
     *
     * A record is identified by its contacts: naming one People already holds
     * answers with the person holding it rather than a second copy of them.
     *
     * @param  array<string, mixed>  $details  `first_name`, `last_name`, `gender`, `date_of_birth`, `address`, and `contacts` as `[['type' => 'email', 'value' => '…']]`.
     *
     * @throws PeopleRefused when People will not take the details, or the caller's token.
     * @throws PeopleUnreachable
     */
    public function record(array $details): Person
    {
        $body = $this->http->json('POST', $this->issuer.'/v1/people', $details, $this->caller->headers());

        return Person::from(Payload::data($body)) ?? throw new PeopleUnreachable('People wrote somebody down and did not say who.');
    }

    /**
     * Who holds a contact — an empty answer where nobody does.
     *
     * A contact can be shared, so this answers with everybody holding it.
     *
     * @throws PeopleRefused|PeopleUnreachable
     */
    public function whoHolds(string $contact): Records
    {
        return $this->lookup(['contact' => $contact]);
    }

    /**
     * The people behind a page of ids, keyed by id.
     *
     * An id People knows nothing about is absent rather than null, and no
     * call is made for an empty list.
     *
     * @param  list<string>  $ids
     *
     * @throws PeopleRefused|PeopleUnreachable
     */
    public function all(array $ids): Records
    {
        $asked = array_values(array_unique(array_filter($ids, fn (string $id): bool => $id !== '')));

        return $asked === [] ? new Records : $this->lookup(['ids' => $asked]);
    }

    /**
     * Fold duplicate records into one, and say which survived.
     *
     * Name the whole set; People keeps the oldest and moves everything else
     * onto it. Null means it folded nothing.
     *
     * @param  list<string>  $ids  Two or more.
     *
     * @throws PeopleRefused when fewer than two are named, or the caller may not merge.
     * @throws PeopleUnreachable
     */
    public function merge(array $ids): ?Person
    {
        $body = $this->http->json(
            'POST',
            $this->issuer.'/v1/manage/people/duplicates/merge',
            ['people' => $ids],
            $this->caller->headers(),
        );

        return Person::from(Payload::data($body));
    }

    /**
     * @param  array<string, string|list<string>>  $query
     *
     * @throws PeopleRefused|PeopleUnreachable
     */
    private function lookup(array $query): Records
    {
        try {
            $body = $this->http->get($this->issuer.'/v1/people/lookup?'.http_build_query($query), $this->caller->headers());
        } catch (PeopleRefused $refused) {
            if ($refused->status === 404) {
                return new Records;
            }

            throw $refused;
        }

        return Records::from($body);
    }
}
