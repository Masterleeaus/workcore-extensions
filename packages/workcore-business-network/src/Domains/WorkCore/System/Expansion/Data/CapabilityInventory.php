<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Expansion\Data;

final readonly class CapabilityInventory
{
    /**
     * @param array<string,array<string,mixed>> $capabilities
     * @param array<string,string> $modules
     * @param array<string,array<string,mixed>> $actions
     * @param array<string,array<string,mixed>> $readModels
     * @param array<int,array<string,mixed>> $sourceFeatures
     * @param array<int,array<string,mixed>> $adaptiveDomains
     * @param array<int,array{capability_key:string,table:string,columns:array<int,string>}> $recordSchemas
     * @param array<int,array<string,mixed>> $adaptiveFeatures
     */
    public function __construct(
        public array $capabilities,
        public array $modules,
        public array $actions,
        public array $readModels,
        public array $sourceFeatures,
        public array $adaptiveDomains,
        public array $recordSchemas = [],
        public array $adaptiveFeatures = [],
    ) {}

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'capabilities' => $this->capabilities,
            'modules' => $this->modules,
            'actions' => $this->actions,
            'read_models' => $this->readModels,
            'source_features' => $this->sourceFeatures,
            'adaptive_domains' => $this->adaptiveDomains,
            'record_schemas' => $this->recordSchemas,
            'adaptive_features' => $this->adaptiveFeatures,
        ];
    }
}
