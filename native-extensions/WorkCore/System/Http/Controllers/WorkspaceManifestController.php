<?php

declare(strict_types=1);

namespace App\Extensions\WorkCore\System\Http\Controllers;

use App\Extensions\WorkCore\System\Navigation\WorkCoreWorkspaceManifest;
use Illuminate\Http\JsonResponse;

final class WorkspaceManifestController
{
    public function __construct(private WorkCoreWorkspaceManifest $manifest) {}

    public function index(): JsonResponse
    {
        return response()->json($this->manifest->forActiveCompany());
    }

    public function show(string $workspace): JsonResponse
    {
        $companyId = $this->manifest->forActiveCompany()['company_id'];
        $definition = $this->manifest->findForCompany((int) $companyId, $workspace);
        if ($definition === null) {
            abort(404, 'The requested WorkCore workspace is not available.');
        }

        return response()->json([
            'company_id' => $companyId,
            'workspace' => $definition,
        ]);
    }
}
