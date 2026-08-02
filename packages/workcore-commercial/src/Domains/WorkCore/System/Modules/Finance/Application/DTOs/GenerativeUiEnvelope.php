<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Application\DTOs;

use App\Domains\WorkCore\System\Modules\Finance\Application\Enums\SurfaceType;
use InvalidArgumentException;

final readonly class GenerativeUiEnvelope
{
    /**
     * @param array<string, mixed> $data
     * @param list<SurfaceType> $surfaces
     * @param list<array<string, mixed>> $actions
     * @param array<string, mixed> $meta
     */
    public function __construct(
        public string $schemaVersion,
        public string $component,
        public array $data,
        public array $surfaces,
        public array $actions,
        public array $meta = [],
    ) {
        if (trim($schemaVersion) === '' || trim($component) === '') {
            throw new InvalidArgumentException('UI schema version and component are required.');
        }

        foreach ($surfaces as $surface) {
            if (! $surface instanceof SurfaceType) {
                throw new InvalidArgumentException('All surfaces must be SurfaceType values.');
            }
        }
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'schema_version' => $this->schemaVersion,
            'component' => $this->component,
            'data' => $this->data,
            'surfaces' => array_values(array_map(
                static fn (SurfaceType $surface): string => $surface->value,
                $this->surfaces,
            )),
            'actions' => array_values($this->actions),
            'meta' => $this->meta,
        ];
    }
}
