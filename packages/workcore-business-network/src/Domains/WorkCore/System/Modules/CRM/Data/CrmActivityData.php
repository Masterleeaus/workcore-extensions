<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\CRM\Data;

final readonly class CrmActivityData
{
    /** @param array<string,mixed> $metadata */
    public function __construct(
        public string $subjectType,
        public string $subjectPublicId,
        public string $type,
        public ?string $title,
        public ?string $body,
        public ?string $direction,
        public ?string $scheduledAt,
        public ?string $completedAt,
        public array $metadata,
    ) {}

    /** @param array<string,mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            trim((string) $data['subject_type']),
            trim((string) $data['subject_public_id']),
            trim((string) ($data['type'] ?? 'note')),
            self::nullable($data['title'] ?? null),
            self::nullable($data['body'] ?? null),
            self::nullable($data['direction'] ?? null),
            self::nullable($data['scheduled_at'] ?? null),
            self::nullable($data['completed_at'] ?? null),
            (array) ($data['metadata'] ?? []),
        );
    }

    private static function nullable(mixed $value): ?string
    {
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }
}
