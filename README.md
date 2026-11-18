# People for PHP

Sign people in with People, verify their tokens, and keep a copy of their identities
current.

People is an OpenID Connect provider. This SDK is a convenience over that protocol,
never a replacement for it: everything it does goes through a documented endpoint,
and any certified OpenID Connect library can do the same. See
[Without the SDK](#without-the-sdk).

```bash
composer require oki-technologies/people-php
```

PHP 8.3+. Bring your own HTTP client (PSR-18), message factories (PSR-17), cache
(PSR-16) and clock (PSR-20). On Laravel, use
`oki-technologies/people-laravel` instead, which wires all of this up.

## Before you start

Register your application at People. You get:

- a **client id**;
- a **client secret**, if your application runs on a server. A browser or mobile app
  gets none and relies on PKCE alone;
- the **redirect URIs** People may send people back to;
- an **API key**, where this service does anything as itself rather than as whoever is
  calling it — minting an invitation ticket, say. It is not a person's token and must
  never stand in for one;
- the **audiences** your tokens may be presented at — the hostnames of the APIs that
  will accept them, e.g. `api.example.com`. Add People's own hostname to read
  identity events.

## Setup

```php
use Oki\People\Config;
use Oki\People\People;

$people = new People(
    new Config(
        issuer: 'https://people.example.com',
        clientId: 'your-client-id',
        clientSecret: getenv('PEOPLE_CLIENT_SECRET'),
        audience: 'api.example.com', // this API's hostname, to verify access tokens
        apiKey: getenv('PEOPLE_API_KEY'), // what this service does as itself, not as somebody
    ),
    $httpClient,      // Psr\Http\Client\ClientInterface
    $requestFactory,  // Psr\Http\Message\RequestFactoryInterface
    $streamFactory,   // Psr\Http\Message\StreamFactoryInterface
    $cache,           // Psr\SimpleCache\CacheInterface — shared across requests
    $clock,           // Psr\Clock\ClockInterface
);
```

Use a cache shared across requests and processes. The key set and the discovery
document are cached there; a cache that lives for one request means a call to People
on every request.

## Verifying an access token

For an API that accepts tokens issued by People:

```php
use Oki\People\Exceptions\TokenRejected;

try {
    $token = $people->accessTokens()->verify($bearerToken);
} catch (TokenRejected $e) {
    // 401. $e->getMessage() says why, for your log — not for the caller.
}

$token->subject;   // People's user id — the same in every application
$token->can('orders.read');
```

Checked locally, with no call to People: the signature against People's published
keys (chosen by the token's `kid`, RS256 only), the expiry, the issuer, and that the
token's `aud` names your `audience`. A token meant for another API is refused, and so
is one naming no audience at all.

What a local check cannot know is revocation: a token People revoked still verifies
until it expires. People's access tokens are short-lived for that reason.

**A token proves who is calling, never what they may do.** Decide that from your own
records.

Key rotation needs nothing from you. A `kid` not seen before is fetched the first time
a token names it (at most once a minute); a key People stops publishing stops
verifying within `keysTtl` seconds (15 minutes by default). While People is
unreachable, the last keys fetched keep working.

### Asking whether a token is still good

Where a local check is not enough — approving a payment, changing where money goes,
acting as somebody else — ask People, which reads the record the token was issued
from:

```php
$answer = $people->introspection()->of($bearerToken);

if (! $answer->active) {
    // Revoked, expired, or never ours. Refuse.
}

$answer->subject;
$answer->can('payments.write');
```

A round trip, on your own client credentials, and only for confidential clients
People's estate registered itself. **Everything else reads your own copy** — this is
the expensive answer, not the default one.

Every way of being no good answers `active: false` and nothing else, so there is one
thing to read.

## Signing somebody in

Authorization code with PKCE (S256), the only flow People offers people:

```php
// 1. Send them to People.
$request = $people->authorization()->begin('https://app.example.com/callback');
$_SESSION['people'] = serialize($request); // state, nonce and verifier — server side only
header('Location: '.$request->url);

// 2. When they come back.
$tokens = $people->authorization()->exchange($_GET, unserialize($_SESSION['people']));

$tokens->accessToken;
$tokens->refreshToken;
$tokens->identity?->subject;  // the verified id_token
$tokens->identity?->name;
```

`exchange()` checks the `state`, trades the code with the verifier, and verifies the
`id_token`: addressed to your client, carrying your `nonce`, bound to the access token
by `at_hash`.

```php
$tokens = $people->authorization()->refresh($refreshToken);
$url = $people->authorization()->signOutUrl($idToken, 'https://app.example.com/');
$identity = $people->userinfo()->of($accessToken); // a round trip; use sparingly
```

## Keeping identities current

A confidential client can read what happened to the people who signed in to it — a
changed name, a suspension, a deletion — and nobody else:

```php
$page = $people->identityEvents()->after($lastSequence);

foreach ($page->events as $event) {
    match ($event->type) {
        IdentityEvent::UPDATED => $users->rename($event->subject, $event->payload['name']),
        IdentityEvent::DELETED => $users->erase($event->subject),
        default => null,
    };
}

if ($page->next !== null) {
    $lastSequence = $page->next; // store it once the page is applied
}
```

Apply events in order. Delivery is at least once, so applying one must be safe to
repeat — `eventId` is there to dedupe on. Once somebody disconnects your application
at People, you hear nothing more about them except their deletion, which you must act
on.

The SDK reads the feed on a token your client holds for itself (the
client-credentials grant, scope `identity.events`). Your client's audiences must
include People's own hostname.

## Asking People about people

Everything past signing in is grouped by **what the call is made on**, because the two
credentials are not interchangeable:

| On | Reach it with | What it does |
| --- | --- | --- |
| The key People issued to this service | `$people->tickets()` | What this service does as itself |
| The access token the caller presented | `$people->caller($token)` | Exactly what People allows *them* |
| Nothing | `$people->profiles()` | What somebody shows the world |

A key that could act as a person is how a key ends up doing something as one, so a
capability holding one cannot reach the other.

### As this service: invitations

```php
$ticket = $people->tickets()->mint(
    personId: $employee->person_id,
    contacts: ['ada@example.com'],
    purpose: 'Joining Example as an engineer.',
    name: 'Ada Lovelace',
);

$ticket->secret;          // send it to them; People shows it once
$ticket->proof->isKnown;  // People already holds somebody under one of those contacts
```

```php
$proof = $people->tickets()->proof($secret);

$proof?->isProved();   // null: People has never heard of it, or it has run out
$proof?->provedBy;     // the account that signed in with it
$proof?->personId;
```

### As the caller: their own record

```php
$caller = $people->caller($accessToken);

$caller->me()->person?->name;
```

```php
$register = $caller->people();

$register->record(['first_name' => 'Ada', 'contacts' => [['type' => 'email', 'value' => 'ada@example.com']]]);
$register->whoHolds('ada@example.com');          // everybody holding it — a contact can be shared
$register->all(['person-1', 'person-2']);        // keyed by the id asked about
$register->merge(['person-1', 'person-2']);      // fold duplicates; People says which survived
```

A record is identified by its contacts, so `record()` with a contact People already
holds answers with the person holding it rather than a second copy of them. An id
People knows nothing about is simply absent from a lookup, never null.

```php
$caller->contacts()->all(search: 'Ada');
$caller->contacts()->file('person-1', 'email', 'ada@example.com', tag: 'work');
$caller->contacts()->change($contactId, value: 'ada@example.org');
$caller->contacts()->drop($contactId);

$caller->profile()->change(['first_name' => 'Ada', 'username' => 'ada']);
```

```php
$caller->passwords()->sendCode();                       // to the caller, always
$set = $caller->passwords()->set($accountId, $code);    // the code proves it is them

$set->password;     // plain text, shown once. Hand it over and keep no copy
$set->mustChange;
```

The code is sent to the caller even when the password being set is somebody else's:
an administrator proves it is them, then acts.

### On nothing: a public profile

```php
$people->profiles()->of('ada')?->name;   // null where nobody holds the handle
```

### When People says no

Every call throws, and which one it throws is the answer:

| | |
| --- | --- |
| `PeopleRefused` | People answered, and the answer was no. `$status`, and `$error` where there is an OAuth code. Wrong tomorrow too: do not ask again |
| `PeopleUnreachable` | People could not be asked, or said nothing usable. Worth asking again |

Where **not there** is an answer rather than a failure — a lapsed ticket, a handle
nobody holds, a contact recorded against nobody — the call answers null or an empty
result instead. A refusal is never flattened into one.

Retrying is the HTTP client's: this SDK sends each call once and says what happened.

## Without the SDK

Everything above is plain OpenID Connect and REST:

| What | Where |
| --- | --- |
| Discovery | `GET {issuer}/.well-known/openid-configuration` |
| Keys | `jwks_uri` from discovery |
| Sign in | `authorization_endpoint`, then `token_endpoint` — authorization code, PKCE S256 |
| Who is signed in | `userinfo_endpoint`, or the `id_token` |
| Sign out | `end_session_endpoint` |
| A client's own token | `token_endpoint`, `grant_type=client_credentials` |
| Identity events | `GET {issuer}/v1/identity/events?after={sequence}&limit={n}` on a client's own token with `identity.events` |
| Invitations | `POST {issuer}/v1/manage/identity/tickets`, `GET …/{ticket}` on this service's API key |
| Who a token is | `GET {issuer}/v1/me` |
| The record | `GET/POST {issuer}/v1/people`, `GET {issuer}/v1/people/lookup`, `POST {issuer}/v1/manage/people/duplicates/merge` |
| A public profile | `GET {issuer}/v1/profiles/{username}` |
| The caller's own | `{issuer}/v1/my/contacts`, `PATCH {issuer}/v1/my/profile`, `POST {issuer}/v1/my/password/code` |
| Setting a password | `PUT {issuer}/v1/manage/users/{user}/password` |

## Versioning

Semantic versioning. A breaking change is a new major version, and People keeps
serving the previous one through a published deprecation window.

## Security

Report a vulnerability privately, through this repository's security advisories,
rather than in a public issue.

## Licence

MIT.
