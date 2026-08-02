<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\CRM\Data;

use App\Domains\WorkCore\System\Modules\CRM\Services\DefaultSalesPipelineProvisioner;
use Illuminate\Support\Str;

final readonly class SalesPipelineData
{
    /** @param list<array<string,mixed>> $stages */
    public function __construct(
        public string $name,
        public string $key,
        public bool $isDefault,
        public array $stages,
    ) {}

    /** @param array<string,mixed> $data */
    public static function fromArray(array $data): self
    {
        $name = trim((string) $data['name']);
        $key = trim((string) ($data['key'] ?? Str::slug($name)));
        $stages = array_values((array) ($data['stages'] ?? DefaultSalesPipelineProvisioner::STAGES));

        return new self($name, $key, (bool) ($data['is_default'] ?? false), $stages);
    }
}
