<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

// Minimal wpdb stub — lets QueryBuilder be instantiated without a full WordPress install.
if (!class_exists('wpdb')) {
    class wpdb
    {
        public string $prefix = 'wp_';

        public function prepare(string $query, mixed ...$args): string
        {
            if (empty($args)) {
                return $query;
            }
            $i = 0;
            return (string) preg_replace_callback(
                '/%(s|d|f)/',
                function () use ($args, &$i): string {
                    return (string) ($args[$i++] ?? '');
                },
                $query
            );
        }

        public function get_results(string $query): array
        {
            return [];
        }

        public function get_var(string $query): mixed
        {
            return null;
        }

        public function insert(string $table, array $data): int|false
        {
            return false;
        }

        public function query(string $query): int|false
        {
            return false;
        }

        public function esc_like(string $text): string
        {
            return addcslashes($text, '_%\\');
        }
    }
}
