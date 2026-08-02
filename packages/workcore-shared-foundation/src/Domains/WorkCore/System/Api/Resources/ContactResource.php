<?php

declare(strict_types=1);
namespace App\Domains\WorkCore\System\Api\Resources;
final class ContactResource
{
    /** @param array<string,mixed> $row @return array<string,mixed> */
    public static function make(array $row): array
    {
        return [
            'id' => (string) ($row['public_id'] ?? ''),
            'type' => 'contact',
            'name' => $row['name'] ?? null,
            'email' => $row['email'] ?? null,
            'phone' => $row['phone'] ?? null,
            'role' => $row['role'] ?? null,
            'is_primary' => (bool) ($row['is_primary'] ?? false),
        ];
    }
}
