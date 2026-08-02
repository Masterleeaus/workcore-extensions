<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Scheduling\Actions;

use App\Domains\WorkCore\System\Actions\ActionHandlerResult;
use App\Domains\WorkCore\System\Actions\ActionRequest;
use App\Domains\WorkCore\System\Actions\Contracts\BusinessActionHandlerContract;
use App\Domains\WorkCore\System\Actions\PendingDomainEvent;
use App\Domains\WorkCore\System\Modules\Scheduling\Contracts\AppointmentRepositoryContract;
use App\Domains\WorkCore\System\References\TypedReference;

final class RescheduleAppointment implements BusinessActionHandlerContract
{
    public function __construct(private AppointmentRepositoryContract $repository) {}

    public function handle(ActionRequest $request): ActionHandlerResult
    {
        $record = $this->repository->reschedule(
            (string) $request->payload['appointment_public_id'],
            $request->payload,
            $request->companyId,
            $request->actorId,
        );

        return new ActionHandlerResult(
            data: $record,
            aggregate: new TypedReference('appointment', (string) $record['public_id']),
            events: [new PendingDomainEvent('workcore.appointment.rescheduled', 1, [
                'appointment_public_id' => $record['public_id'],
                'starts_at' => $record['starts_at'] ?? null,
                'ends_at' => $record['ends_at'] ?? null,
                'timezone' => $record['timezone'] ?? null,
                'reason' => $request->payload['reason'] ?? null,
                'assets' => $record['assets'] ?? [],
            ])],
        );
    }
}
