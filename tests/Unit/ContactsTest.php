<?php

declare(strict_types=1);

use Nyholm\Psr7\Response;
use Oki\People\Exceptions\PeopleRefused;
use Oki\People\Exceptions\PeopleUnreachable;

const MY_CONTACTS = '/v1/my/contacts';

beforeEach(function () {
    $this->stage = stage();
    $this->contacts = $this->stage->people->caller('access-1')->contacts();
});

it('reads the contacts the caller keeps', function () {
    $this->stage->http->on(MY_CONTACTS, ['data' => [
        ['id' => 'contact-1', 'type' => 'email', 'value' => 'ada@example.com', 'tag' => 'work', 'is_primary' => true, 'is_verified' => true, 'created_at' => '2026-01-04T09:00:00+00:00'],
        ['id' => 'contact-2', 'type' => 'phone', 'value' => '+2348000000000'],
        ['type' => 'phone', 'value' => 'no id'],
    ]]);

    $contacts = $this->contacts->all();

    expect($contacts)->toHaveCount(2)
        ->and($contacts[0]->tag)->toBe('work')
        ->and($contacts[0]->isPrimary)->toBeTrue()
        ->and($contacts[0]->isVerified)->toBeTrue()
        ->and($contacts[0]->createdAt?->format('c'))->toBe('2026-01-04T09:00:00+00:00')
        ->and($contacts[1]->isPrimary)->toBeFalse()
        ->and($this->stage->http->last(MY_CONTACTS)?->getHeaderLine('Authorization'))->toBe('Bearer access-1')
        ->and($this->stage->http->last(MY_CONTACTS)?->getUri()->getQuery())->toBe('');
});

it('narrows them by who they belong to', function () {
    $this->stage->http->on(MY_CONTACTS, ['data' => []]);

    $this->contacts->all('Ada');

    expect($this->stage->http->last(MY_CONTACTS)?->getUri()->getQuery())->toBe('search=Ada');
});

it('files a contact against a record', function () {
    $this->stage->http->on(MY_CONTACTS, ['data' => ['id' => 'contact-1', 'type' => 'email', 'value' => 'ada@example.com']], 201);

    $contact = $this->contacts->file('person-1', 'email', 'ada@example.com', 'work', true);

    expect($contact->id)->toBe('contact-1')
        ->and(json_decode((string) $this->stage->http->last(MY_CONTACTS)?->getBody(), true))
        ->toBe(['person_id' => 'person-1', 'type' => 'email', 'value' => 'ada@example.com', 'tag' => 'work', 'is_primary' => true]);
});

it('files one without a tag or a say about which is primary', function () {
    $this->stage->http->on(MY_CONTACTS, ['data' => ['id' => 'contact-1', 'type' => 'email', 'value' => 'ada@example.com']], 201);

    $this->contacts->file('person-1', 'email', 'ada@example.com');

    expect(json_decode((string) $this->stage->http->last(MY_CONTACTS)?->getBody(), true))
        ->toBe(['person_id' => 'person-1', 'type' => 'email', 'value' => 'ada@example.com']);
});

it('refuses to file against a record the caller does not keep', function () {
    $this->stage->http->on(MY_CONTACTS, ['message' => 'This action is unauthorized.'], 403);

    $this->contacts->file('person-9', 'email', 'ada@example.com');
})->throws(PeopleRefused::class);

it('says so when People files a contact and answers with none', function () {
    $this->stage->http->on(MY_CONTACTS, ['data' => ['type' => 'email']], 201);

    $this->contacts->file('person-1', 'email', 'ada@example.com');
})->throws(PeopleUnreachable::class, 'People answered with no contact.');

it('changes one', function () {
    $this->stage->http->on(MY_CONTACTS.'/contact-1', ['data' => ['id' => 'contact-1', 'type' => 'email', 'value' => 'ada@example.org']]);

    $contact = $this->contacts->change('contact-1', 'ada@example.org', 'home', false);
    $asked = $this->stage->http->last(MY_CONTACTS.'/contact-1');

    expect($contact->value)->toBe('ada@example.org')
        ->and($asked?->getMethod())->toBe('PATCH')
        ->and(json_decode((string) $asked?->getBody(), true))->toBe(['value' => 'ada@example.org', 'tag' => 'home', 'is_primary' => false]);
});

it('changes nothing it was not asked to', function () {
    $this->stage->http->on(MY_CONTACTS.'/contact-1', ['data' => ['id' => 'contact-1', 'type' => 'email', 'value' => 'ada@example.com']]);

    $this->contacts->change('contact-1');

    expect((string) $this->stage->http->last(MY_CONTACTS.'/contact-1')?->getBody())->toBe('{}');
});

it('drops one', function () {
    $this->stage->http->on(MY_CONTACTS.'/contact-1', fn (): Response => new Response(204));

    $this->contacts->drop('contact-1');

    expect($this->stage->http->last(MY_CONTACTS.'/contact-1')?->getMethod())->toBe('DELETE');
});

it('says so when People cannot be reached about contacts', function () {
    $this->stage->http->down(MY_CONTACTS);

    $this->contacts->all();
})->throws(PeopleUnreachable::class);
