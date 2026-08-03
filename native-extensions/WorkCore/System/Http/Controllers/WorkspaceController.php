<?php

declare(strict_types=1);

namespace App\Extensions\WorkCore\System\Http\Controllers;

use App\Extensions\WorkCore\System\Navigation\WorkCoreWorkspaceCatalogue;
use App\Extensions\WorkCore\System\Navigation\WorkCoreWorkspaceManifest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

final class WorkspaceController
{
    public function __construct(
        private WorkCoreWorkspaceCatalogue $catalogue,
        private WorkCoreWorkspaceManifest $manifest,
    ) {}

    public function show(Request $request): View
    {
        $workspaceKey = (string) $request->route('workspace');
        $sectionKey = $request->route('section');
        $workspaceDefinition = $this->catalogue->workspace($workspaceKey);
        if ($workspaceDefinition === null) {
            abort(404, 'Unknown WorkCore workspace.');
        }
        if ($sectionKey !== null && $this->catalogue->section($workspaceKey, (string) $sectionKey) === null) {
            abort(404, 'Unknown WorkCore workspace section.');
        }

        $manifest = $this->manifest->forActiveCompany();
        $workspace = null;
        foreach ($manifest['workspaces'] as $candidate) {
            if (($candidate['key'] ?? null) === $workspaceKey) {
                $workspace = $candidate;
                break;
            }
        }
        if ($workspace === null) {
            abort(404, 'The requested WorkCore workspace is not available.');
        }

        $selected = $workspace;
        if ($sectionKey !== null) {
            $selected = null;
            foreach ($workspace['sections'] as $candidate) {
                if (($candidate['key'] ?? null) === $sectionKey) {
                    $selected = $candidate;
                    break;
                }
            }
            if ($selected === null) {
                abort(404, 'The requested WorkCore workspace section is not available.');
            }
        }

        return view('workcore::workspace', [
            'companyId' => $manifest['company_id'],
            'entitlementRevision' => $manifest['entitlement_revision'],
            'workspace' => $workspace,
            'sections' => $workspace['sections'],
            'selected' => $selected,
        ]);
    }
}
