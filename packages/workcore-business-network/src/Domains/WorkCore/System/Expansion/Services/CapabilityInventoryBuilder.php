<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Expansion\Services;

use App\Domains\WorkCore\System\Actions\BusinessActionRegistry;
use App\Domains\WorkCore\System\Capabilities\CapabilityRegistry;
use App\Domains\WorkCore\System\Donors\FeatureSourceRegistry;
use App\Domains\WorkCore\System\Expansion\Contracts\ExpansionRepositoryContract;
use App\Domains\WorkCore\System\Expansion\Contracts\AdaptiveFeatureRepositoryContract;
use App\Domains\WorkCore\System\Expansion\Data\CapabilityInventory;
use App\Domains\WorkCore\System\ReadModels\ReadModelRegistry;
use App\Domains\WorkCore\System\Registry\WorkModuleRegistry;

final class CapabilityInventoryBuilder
{
    public function __construct(
        private CapabilityRegistry $capabilities,
        private WorkModuleRegistry $modules,
        private BusinessActionRegistry $actions,
        private ReadModelRegistry $readModels,
        private FeatureSourceRegistry $sourceFeatures,
        private ExpansionRepositoryContract $repository,
        private AdaptiveFeatureRepositoryContract $featureRepository,
        private ConfiguredRecordSchemaScanner $recordSchemas,
    ) {}

    public function build(int $companyId): CapabilityInventory
    {
        $capabilities = [];
        foreach ($this->capabilities->all() as $key => $definition) {
            $capabilities[$key] = $definition->toArray();
        }

        $actions = [];
        foreach ($this->actions->all() as $key => $definition) {
            $actions[$key] = [
                'key' => $key,
                'risk' => $definition->risk,
                'confirmation_required' => $definition->requiresConfirmation,
                'capability' => $definition->capability,
                'permission' => $definition->permission,
                'metadata' => $definition->metadata,
            ];
        }

        $reads = [];
        foreach ($this->readModels->all() as $key => $definition) {
            $reads[$key] = [
                'key' => $key,
                'capability' => $definition->capability,
                'metadata' => $definition->metadata,
            ];
        }

        $sourceFeatures = array_map(
            static fn (object $definition): array => $definition->toArray(),
            $this->sourceFeatures->all(),
        );

        return new CapabilityInventory(
            capabilities: $capabilities,
            modules: $this->modules->all(),
            actions: $actions,
            readModels: $reads,
            sourceFeatures: $sourceFeatures,
            adaptiveDomains: $this->repository->activeDomains($companyId),
            recordSchemas: $this->recordSchemas->scan(),
            adaptiveFeatures: $this->featureRepository->listFeatures($companyId, 'active'),
        );
    }
}
