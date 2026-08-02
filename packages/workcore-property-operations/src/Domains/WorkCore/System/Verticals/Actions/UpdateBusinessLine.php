<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Verticals\Actions;

use App\Domains\WorkCore\System\Actions\{ActionHandlerResult,ActionRequest,PendingDomainEvent};
use App\Domains\WorkCore\System\Actions\Contracts\BusinessActionHandlerContract;
use App\Domains\WorkCore\System\Models\BusinessLine;
use App\Domains\WorkCore\System\References\TypedReference;
use App\Domains\WorkCore\System\Verticals\CompanyVerticalProfileResolver;
use DomainException;
use Illuminate\Database\ConnectionInterface;

final class UpdateBusinessLine implements BusinessActionHandlerContract
{
    public function __construct(
        private ConnectionInterface $db,
        private CompanyVerticalProfileResolver $profiles,
    ) {}

    public function handle(ActionRequest $request): ActionHandlerResult
    {
        $publicId = (string) $request->payload['business_line_public_id'];
        $line = BusinessLine::query()
            ->where('company_id', $request->companyId)
            ->where('public_id', $publicId)
            ->firstOrFail();

        $updated = $this->db->transaction(function () use ($request, $line): BusinessLine {
            $status = (string) ($request->payload['status'] ?? $line->status);
            $makeDefault = (bool) ($request->payload['is_default'] ?? $line->is_default);
            if ($status !== 'active' && $makeDefault) {
                throw new DomainException('An inactive business line cannot be the company default.');
            }
            if ($makeDefault) {
                BusinessLine::query()
                    ->where('company_id', $request->companyId)
                    ->where('id', '<>', $line->getKey())
                    ->update(['is_default' => false]);
            }
            $line->fill(array_filter([
                'name' => isset($request->payload['name']) ? trim((string) $request->payload['name']) : null,
                'status' => $status,
                'is_default' => $makeDefault,
                'settings' => array_key_exists('settings', $request->payload) ? (array) $request->payload['settings'] : null,
            ], static fn (mixed $value): bool => $value !== null));
            $line->save();

            return $line->refresh();
        }, 3);

        $this->profiles->forget($request->companyId);
        $data = [
            'public_id' => $updated->public_id,
            'key' => $updated->key,
            'name' => $updated->name,
            'status' => $updated->status,
            'is_default' => (bool) $updated->is_default,
            'settings' => (array) ($updated->settings ?? []),
        ];

        return new ActionHandlerResult(
            $data,
            new TypedReference('business_line', $publicId),
            [new PendingDomainEvent('workcore.business_line.updated', 1, ['business_line' => $data])],
        );
    }
}
