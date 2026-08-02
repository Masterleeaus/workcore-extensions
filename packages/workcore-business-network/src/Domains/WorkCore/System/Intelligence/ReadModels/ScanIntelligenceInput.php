<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Intelligence\ReadModels;

use App\Domains\WorkCore\System\Contracts\PermissionResolverContract;
use App\Domains\WorkCore\System\Intelligence\Safety\ContentSafetyScanner;
use InvalidArgumentException;

final class ScanIntelligenceInput
{
    public function __construct(
        private ContentSafetyScanner $scanner,
        private PermissionResolverContract $permissions,
    ) {}

    /** @return array<string,mixed> */
    public function execute(string $content, string $trustBoundary = 'external_model'): array
    {
        $tenant = workcore_tenant();
        $companyId = $tenant->companyId();
        $actorId = $tenant->userId();
        if ($companyId === null || $actorId === null) {
            abort(403);
        }
        abort_unless($this->permissions->allows($actorId, $companyId, (string) config('workcore.intelligence.permissions.view')), 403);
        if (trim($content) === '' || strlen($content) > 100000) {
            throw new InvalidArgumentException('Safety scan content must contain between 1 and 100000 characters.');
        }

        return $this->scanner->scan($content, $trustBoundary)->toArray();
    }
}
