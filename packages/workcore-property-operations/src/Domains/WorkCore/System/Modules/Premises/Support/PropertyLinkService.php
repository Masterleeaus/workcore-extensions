<?php
namespace App\Domains\WorkCore\System\Modules\Premises\Support;

/**
 * Stub for later deep links to Core Job/Work Order/Quote/Invoice.
 * Keeps ManagedPremises independent for now.
 */
class PropertyLinkService
{
    public function makeLink(string $module, int $id): array
    {
        return ['linked_module' => $module, 'linked_id' => $id];
    }
}
