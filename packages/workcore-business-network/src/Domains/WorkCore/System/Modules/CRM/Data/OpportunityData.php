<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\CRM\Data;

final readonly class OpportunityData
{
    /** @param array<string,mixed> $metadata */
    public function __construct(
        public string $pipelinePublicId,
        public string $stagePublicId,
        public string $title,
        public ?string $description,
        public float $amount,
        public string $currencyCode,
        public ?int $probability,
        public ?string $expectedCloseDate,
        public ?string $leadPublicId,
        public ?string $customerPublicId,
        public ?string $contactPublicId,
        public ?int $ownerUserId,
        public array $metadata,
    ) {}

    /** @param array<string,mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            trim((string) $data['pipeline_public_id']),
            trim((string) $data['stage_public_id']),
            trim((string) $data['title']),
            self::nullableString($data['description'] ?? null),
            round((float) ($data['amount'] ?? 0), 2),
            strtoupper(trim((string) ($data['currency_code'] ?? 'AUD'))),
            array_key_exists('probability', $data) && $data['probability'] !== null ? (int) $data['probability'] : null,
            self::nullableString($data['expected_close_date'] ?? null),
            self::nullableString($data['lead_public_id'] ?? null),
            self::nullableString($data['customer_public_id'] ?? null),
            self::nullableString($data['contact_public_id'] ?? null),
            isset($data['owner_user_id']) ? (int) $data['owner_user_id'] : null,
            (array) ($data['metadata'] ?? []),
        );
    }

    private static function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }
}
