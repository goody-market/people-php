<?php

declare(strict_types=1);

namespace Oki\People\Register;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use Oki\People\Support\Payload;
use Traversable;

/**
 * The people a lookup found, keyed by the id they were asked about.
 *
 * Nobody is missing from it and nobody is null: an id People knows nothing
 * about is simply not there, which is the answer to "who is this?" for an id
 * that is gone.
 *
 * @implements IteratorAggregate<string, Person>
 */
final readonly class Records implements Countable, IteratorAggregate
{
    /**
     * @param  array<string, Person>  $people
     */
    public function __construct(public array $people = []) {}

    /**
     * @param  array<mixed>  $body
     */
    public static function from(array $body): self
    {
        $people = [];

        foreach (Payload::rows($body) as $row) {
            $person = Person::from($row);

            if ($person !== null) {
                $people[$person->id] = $person;
            }
        }

        return new self($people);
    }

    public function find(string $id): ?Person
    {
        return $this->people[$id] ?? null;
    }

    /** @return list<Person> */
    public function all(): array
    {
        return array_values($this->people);
    }

    public function count(): int
    {
        return count($this->people);
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->people);
    }
}
