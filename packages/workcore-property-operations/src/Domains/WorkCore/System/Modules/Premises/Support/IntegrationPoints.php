<?php
namespace App\Domains\WorkCore\System\Modules\Premises\Support;

/**
 * A lightweight registry of integration points for module builders.
 * This is documentation-in-code (stable contract), not runtime magic.
 *
 * NOTE: Endpoints are sourced from the canonical platform route inventory JSON.
 */
class IntegrationPoints
{
    public static function all(): array
    {
        return [
            'events' => [
                'Modules\\ManagedPremises\\Events\\PropertyVisitScheduled',
                'Modules\\ManagedPremises\\Events\\PropertyVisitCompleted',
            ],
            'adapters' => [
                'Modules\\ManagedPremises\\Integrations\\Core\\TaskAdapterInterface',
                'Modules\\ManagedPremises\\Integrations\\Core\\HrAdapterInterface',
            ],
            'endpoints' => [
                'API GET /calendar-feed',
                'WEB GET /',
                'WEB GET /calendar',
                'WEB GET /properties',
                'WEB POST /properties',
                'WEB GET /properties.inspections',
                'WEB POST /properties.inspections',
                'WEB GET /properties.inspections/create',
                'WEB DELETE /properties.inspections/{properties.inspections}',
                'WEB GET /properties.inspections/{properties.inspections}',
                'WEB PATCH /properties.inspections/{properties.inspections}',
                'WEB PUT /properties.inspections/{properties.inspections}',
                'WEB GET /properties.inspections/{properties.inspections}/edit',
                'WEB GET /properties.service-plans',
                'WEB POST /properties.service-plans',
                'WEB GET /properties.service-plans/create',
                'WEB DELETE /properties.service-plans/{properties.service-plans}',
                'WEB GET /properties.service-plans/{properties.service-plans}',
                'WEB PATCH /properties.service-plans/{properties.service-plans}',
                'WEB PUT /properties.service-plans/{properties.service-plans}',
                'WEB GET /properties.service-plans/{properties.service-plans}/edit',
                'WEB GET /properties.visits',
                'WEB POST /properties.visits',
                'WEB GET /properties.visits/create',
                'WEB DELETE /properties.visits/{properties.visits}',
                'WEB GET /properties.visits/{properties.visits}',
                'WEB PATCH /properties.visits/{properties.visits}',
                'WEB PUT /properties.visits/{properties.visits}',
                'WEB GET /properties.visits/{properties.visits}/edit',
                'WEB GET /properties/create',
                'WEB DELETE /properties/{properties}',
                'WEB GET /properties/{properties}',
                'WEB PATCH /properties/{properties}',
                'WEB PUT /properties/{properties}',
                'WEB GET /properties/{properties}/edit',
                'WEB GET /properties/{property}/approvals',
                'WEB POST /properties/{property}/approvals',
                'WEB POST /properties/{property}/approvals/{approval}/decision',
                'WEB GET /properties/{property}/assets',
                'WEB POST /properties/{property}/assets',
                'WEB DELETE /properties/{property}/assets/{asset}',
                'WEB GET /properties/{property}/checklists',
                'WEB POST /properties/{property}/checklists',
                'WEB DELETE /properties/{property}/checklists/{checklist}',
                'WEB GET /properties/{property}/contacts',
                'WEB POST /properties/{property}/contacts',
                'WEB DELETE /properties/{property}/contacts/{contact}',
                'WEB GET /properties/{property}/documents',
                'WEB POST /properties/{property}/documents',
                'WEB DELETE /properties/{property}/documents/{document}',
                'WEB GET /properties/{property}/documents/{document}',
                'WEB GET /properties/{property}/hazards',
                'WEB POST /properties/{property}/hazards',
                'WEB DELETE /properties/{property}/hazards/{hazard}',
                'WEB GET /properties/{property}/jobs',
                'WEB POST /properties/{property}/jobs',
                'WEB DELETE /properties/{property}/jobs/{job}',
                'WEB GET /properties/{property}/keys',
                'WEB POST /properties/{property}/keys',
                'WEB DELETE /properties/{property}/keys/{key}',
                'WEB GET /properties/{property}/overview',
                'WEB GET /properties/{property}/photos',
                'WEB POST /properties/{property}/photos',
                'WEB DELETE /properties/{property}/photos/{photo}',
                'WEB GET /properties/{property}/rooms',
                'WEB POST /properties/{property}/rooms',
                'WEB DELETE /properties/{property}/rooms/{room}',
                'WEB GET /properties/{property}/service-windows',
                'WEB POST /properties/{property}/service-windows',
                'WEB DELETE /properties/{property}/service-windows/{window}',
                'WEB GET /properties/{property}/tags',
                'WEB POST /properties/{property}/tags',
                'WEB DELETE /properties/{property}/tags/{tag}',
                'WEB GET /properties/{property}/units',
                'WEB POST /properties/{property}/units',
                'WEB DELETE /properties/{property}/units/{unit}',
                'WEB GET /settings',
                'WEB POST /settings',
            ],
            'commands' => [
                'pm:generate-visits --company_id=<id> --days=30',
                'pm:doctor',
            ],
        ];
    }
}
