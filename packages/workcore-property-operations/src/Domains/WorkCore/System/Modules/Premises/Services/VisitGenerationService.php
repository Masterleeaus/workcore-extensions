<?php
namespace App\Domains\WorkCore\System\Modules\Premises\Services;

use Illuminate\Support\Facades\DB;
use App\Domains\WorkCore\System\Modules\Premises\Entities\PropertyServicePlan;
use App\Domains\WorkCore\System\Modules\Premises\Entities\PropertyVisit;
use App\Domains\WorkCore\System\Modules\Premises\Support\RecurrenceService;
use DateTimeImmutable;
use DateTimeInterface;

/**
 * Generates upcoming visits from active service plans.
 * Safe rules:
 * - Tenant-scoped by company_id
 * - Never creates duplicates for same plan_id + scheduled_for
 * - Conservative RRULE support (see RecurrenceService)
 */
class VisitGenerationService
{
    public function __construct(protected RecurrenceService $recurrence) {}

    public function generateForCompany(int $companyId, DateTimeInterface $from, DateTimeInterface $until, int $limitPerPlan = 30): int
    {
        $count = 0;

        $plans = PropertyServicePlan::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->get();

        foreach ($plans as $plan) {
            $start = $plan->starts_on ? new DateTimeImmutable($plan->starts_on->format('Y-m-d') . ' 09:00:00') : new DateTimeImmutable('now');
            $occ = $this->recurrence->nextOccurrences($plan->rrule, $start, $from, $until, $limitPerPlan);

            foreach ($occ as $dt) {
                $scheduled = $dt->format('Y-m-d H:i:s');

                $exists = PropertyVisit::query()
                    ->where('company_id', $companyId)
                    ->where('service_plan_id', $plan->id)
                    ->where('scheduled_for', $scheduled)
                    ->exists();

                if ($exists) continue;

                DB::transaction(function () use ($plan, $scheduled, &$count, $companyId) {
                    PropertyVisit::create([
                        'company_id' => $companyId,
                        'property_id' => $plan->property_id,
                        'service_plan_id' => $plan->id,
                        'visit_type' => $plan->service_type ?: 'service',
                        'scheduled_for' => $scheduled,
                        'assigned_to' => null,
                        'status' => 'scheduled',
                        'notes' => $plan->notes,
                        'completed_at' => null,
                    ]);
                    $count++;
                });
            }
        }

        return $count;
    }
}
