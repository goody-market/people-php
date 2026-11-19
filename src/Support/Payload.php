<?php

declare(strict_types=1);

namespace Oki\People\Support;

use DateTimeImmutable;

/**
 * Reading People's answers without trusting their shape.
 *
 * Every REST answer is a resource under `data`, so that is what is read, with
 * the root as a fallback for an answer that was never wrapped. A field of the
 * wrong type is absent rather than fatal: one changed answer must not take an
 * application down.
 */
final class Payload
{
    /**
     * The resource an answer carries.
     *
     * @param  array<mixed>  $body
     * @return array<string, mixed>
     */
    public static function data(array $body): array
    {
        $data = $body['data'] ?? $body;

        return is_array($data) ? self::named($data) : [];
    }

    /**
     * The resources an answer carries, whether it holds a list or a single one.
     *
     * @param  array<mixed>  $body
     * @return list<array<string, mixed>>
     */
    public static function rows(array $body): array
    {
        $data = $body['data'] ?? $body;

        if (! is_array($data)) {
            return [];
        }

        if (array_key_exists('id', $data)) {
            return [self::named($data)];
        }

        $rows = [];

        foreach ($data as $row) {
            if (is_array($row)) {
                $rows[] = self::named($row);
            }
        }

        return $rows;
    }

    /**
     * A resource nested inside another, wrapped or not.
     *
     * @param  array<string, mixed>  $row
     * @return list<array<string, mixed>>
     */
    public static function nested(array $row, string $field): array
    {
        $value = $row[$field] ?? null;

        return is_array($value) ? self::rows($value) : [];
    }

    /**
     * Text, or null where there is none to read.
     *
     * @param  array<string, mixed>  $row
     */
    public static function text(array $row, string $field): ?string
    {
        $value = $row[$field] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * A yes or a no, falling back where People said neither.
     *
     * @param  array<string, mixed>  $row
     */
    public static function flag(array $row, string $field, bool $fallback = false): bool
    {
        $value = $row[$field] ?? null;

        return is_bool($value) ? $value : $fallback;
    }

    /**
     * A moment, or null where there is none to read.
     *
     * @param  array<string, mixed>  $row
     */
    public static function moment(array $row, string $field): ?DateTimeImmutable
    {
        $value = self::text($row, $field);
        $at = $value === null ? false : date_create_immutable($value);

        return $at === false ? null : $at;
    }

    /**
     * A list of words — role names, permission names.
     *
     * @param  array<string, mixed>  $row
     * @return list<string>
     */
    public static function words(array $row, string $field): array
    {
        $value = $row[$field] ?? null;

        return is_array($value) ? array_values(array_filter($value, is_string(...))) : [];
    }

    /**
     * The field-by-field reasons of a refusal: field => messages.
     *
     * A field whose value is not a list of text keeps only the text it has, and
     * is dropped when there is none.
     *
     * @param  mixed  $errors
     * @return array<string, list<string>>
     */
    public static function reasons(mixed $errors): array
    {
        if (! is_array($errors)) {
            return [];
        }

        $reasons = [];

        foreach (self::named($errors) as $field => $messages) {
            $kept = is_string($messages) ? [$messages] : (is_array($messages) ? self::words(['m' => $messages], 'm') : []);
            $kept = array_values(array_filter($kept, fn (string $message): bool => $message !== ''));

            if ($kept !== []) {
                $reasons[$field] = $kept;
            }
        }

        return $reasons;
    }

    /**
     * Only the fields People named, so a list never passes for a resource.
     *
     * @param  array<mixed>  $value
     * @return array<string, mixed>
     */
    public static function named(array $value): array
    {
        /** @var array<string, mixed> $named */
        $named = array_filter($value, is_string(...), ARRAY_FILTER_USE_KEY);

        return $named;
    }
}
