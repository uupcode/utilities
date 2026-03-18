<?php

declare(strict_types=1);

namespace UupCode\Utilities;

/**
 * Unified static facade for Post / User / Term / Comment meta.
 *
 * Usage:
 *
 *   Meta::post(5)->get('_price');
 *   Meta::post(5)->all('_price');        // all values for key (third arg true)
 *   Meta::post(5)->every();              // all meta for the object
 *   Meta::post(5)->has('_price');
 *   Meta::post(5)->set('_price', 99.99);
 *   Meta::post(5)->add('_tag', 'red');   // allows duplicates
 *   Meta::post(5)->delete('_price');
 *   Meta::post(5)->delete('_tag', 'red');
 *   Meta::post(5)->setMany(['_price' => 99, '_sku' => 'ABC']);
 *
 *   Meta::user($userId)->get('_billing_country');
 *   Meta::term($termId)->set('_icon', 'star');
 *   Meta::comment($commentId)->get('_rating');
 */
final class Meta
{
    public static function post(int $objectId): MetaProxy
    {
        return new MetaProxy('post', $objectId);
    }

    public static function user(int $objectId): MetaProxy
    {
        return new MetaProxy('user', $objectId);
    }

    public static function term(int $objectId): MetaProxy
    {
        return new MetaProxy('term', $objectId);
    }

    public static function comment(int $objectId): MetaProxy
    {
        return new MetaProxy('comment', $objectId);
    }
}

/**
 * Proxy instance returned by Meta::post() / user() / term() / comment().
 */
final class MetaProxy
{
    public function __construct(
        private readonly string $type,
        private readonly int    $objectId,
    ) {
    }

    /**
     * Get a single meta value (first value for the key).
     */
    public function get(string $key): mixed
    {
        return get_metadata($this->type, $this->objectId, $key, true);
    }

    /**
     * Get all values for a meta key (may be multiple rows).
     *
     * @return list<mixed>
     */
    public function all(string $key): array
    {
        $values = get_metadata($this->type, $this->objectId, $key, false);
        return is_array($values) ? array_values($values) : [];
    }

    /**
     * Get every meta entry for the object (no key filter).
     *
     * @return array<string, list<mixed>>
     */
    public function every(): array
    {
        $raw = get_metadata($this->type, $this->objectId);
        if (! is_array($raw)) {
            return [];
        }

        $result = [];
        foreach ($raw as $key => $values) {
            $result[ $key ] = array_map('maybe_unserialize', (array) $values);
        }
        return $result;
    }

    /**
     * Check whether a meta key exists.
     */
    public function has(string $key): bool
    {
        return metadata_exists($this->type, $this->objectId, $key);
    }

    /**
     * Update (or create) a meta value.
     */
    public function set(string $key, mixed $value): bool
    {
        return (bool) update_metadata($this->type, $this->objectId, $key, $value);
    }

    /**
     * Add a meta value, allowing duplicate keys.
     */
    public function add(string $key, mixed $value): int|false
    {
        return add_metadata($this->type, $this->objectId, $key, $value);
    }

    /**
     * Delete meta. Passing $value removes only rows matching that value;
     * omitting it removes all rows for the key.
     */
    public function delete(string $key, mixed $value = ''): bool
    {
        return delete_metadata($this->type, $this->objectId, $key, $value);
    }

    /**
     * Update multiple meta keys in one call.
     *
     * @param array<string, mixed> $data
     */
    public function setMany(array $data): void
    {
        foreach ($data as $key => $value) {
            update_metadata($this->type, $this->objectId, $key, $value);
        }
    }
}
