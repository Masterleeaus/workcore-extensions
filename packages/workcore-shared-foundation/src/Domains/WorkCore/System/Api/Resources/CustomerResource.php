<?php

declare(strict_types=1);
namespace App\Domains\WorkCore\System\Api\Resources;
final class CustomerResource
{
    /** @param array<string,mixed> $row @return array<string,mixed> */
    public static function make(array $row): array
    {
        return [
            'id' => (string) ($row['public_id'] ?? ''),
            'type' => 'customer',
            'name' => $row['name'] ?? null,
            'legal_name' => $row['company_name'] ?? $row['legal_name'] ?? null,
            'email' => $row['email'] ?? null,
            'phone' => $row['phone'] ?? null,
            'address' => $row['address'] ?? null,
            'notes' => $row['note'] ?? $row['notes'] ?? null,
            'duplicate_candidates' => $row['duplicate_candidates'] ?? [],
        ];
    }
}
