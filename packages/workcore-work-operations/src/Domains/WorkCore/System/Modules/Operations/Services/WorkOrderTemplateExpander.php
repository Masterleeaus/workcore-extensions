<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Operations\Services;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class WorkOrderTemplateExpander
{
    public function __construct(private ConnectionInterface $db) {}

    /** @param array<string,mixed> $serviceLine */
    public function expand(int $companyId, int $workOrderId, int $actorId, array $serviceLine, int $sortOrder, ?int $businessLineId = null): void
    {
        $service = $this->db->table('tz_services')
            ->where('company_id', $companyId)
            ->where('public_id', (string) ($serviceLine['service_public_id'] ?? ''))
            ->whereNull('deleted_at')
            ->first(['id', 'business_line_id', 'code', 'name', 'description', 'default_duration_minutes', 'base_price']);
        if (! $service) {
            throw new InvalidArgumentException('A selected service does not belong to the active company.');
        }
        $serviceBusinessLineId = $service->business_line_id === null ? null : (int) $service->business_line_id;
        if ($businessLineId === null && $serviceBusinessLineId !== null) {
            throw new InvalidArgumentException('A business-line service requires a business-line work order.');
        }
        if ($businessLineId !== null && $serviceBusinessLineId !== null && $serviceBusinessLineId !== $businessLineId) {
            throw new InvalidArgumentException('A selected service belongs to a different business line.');
        }

        $template = $this->resolveTemplate($companyId, (int) $service->id, $serviceLine['job_template_public_id'] ?? null);
        $templateBusinessLineId = $template?->business_line_id === null ? null : (int) $template->business_line_id;
        if ($businessLineId === null && $templateBusinessLineId !== null) {
            throw new InvalidArgumentException('A business-line job template requires a business-line work order.');
        }
        if ($businessLineId !== null && $templateBusinessLineId !== null && $templateBusinessLineId !== $businessLineId) {
            throw new InvalidArgumentException('Job template belongs to a different business line.');
        }
        $quantity = (float) ($serviceLine['quantity'] ?? 1);
        if ($quantity <= 0) {
            throw new InvalidArgumentException('Work-order service quantity must be greater than zero.');
        }

        $serviceLineId = $this->db->table('tz_work_order_services')->insertGetId([
            'public_id' => (string) Str::ulid(),
            'company_id' => $companyId,
            'work_order_id' => $workOrderId,
            'service_id' => $service->id,
            'job_template_id' => $template?->id,
            'service_code' => $service->code,
            'service_name' => $service->name,
            'description' => $serviceLine['description'] ?? $service->description,
            'quantity' => $quantity,
            'unit_price' => $serviceLine['unit_price'] ?? $service->base_price,
            'estimated_duration_minutes' => $serviceLine['estimated_duration_minutes']
                ?? $template?->estimated_duration_minutes
                ?? ($service->default_duration_minutes ? (int) round($service->default_duration_minutes * $quantity) : null),
            'sort_order' => $serviceLine['sort_order'] ?? $sortOrder,
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if (! $template) {
            return;
        }

        $taskTemplates = $this->db->table('tz_task_templates')
            ->where('company_id', $companyId)
            ->where('job_template_id', $template->id)
            ->where('active', true)
            ->orderBy('sort_order')
            ->get(['id', 'name', 'description', 'sort_order', 'estimated_minutes', 'is_required']);

        foreach ($taskTemplates as $taskTemplate) {
            $this->db->table('tz_work_order_tasks')->insert([
                'public_id' => (string) Str::ulid(),
                'company_id' => $companyId,
                'work_order_id' => $workOrderId,
                'work_order_service_id' => $serviceLineId,
                'source_task_template_id' => $taskTemplate->id,
                'name' => $taskTemplate->name,
                'description' => $taskTemplate->description,
                'sort_order' => $taskTemplate->sort_order,
                'estimated_minutes' => $taskTemplate->estimated_minutes,
                'is_required' => $taskTemplate->is_required,
                'status' => 'pending',
                'created_by_user_id' => $actorId,
                'updated_by_user_id' => $actorId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function resolveTemplate(int $companyId, int $serviceId, mixed $templatePublicId): ?object
    {
        $query = $this->db->table('tz_job_templates')
            ->where('company_id', $companyId)
            ->where('service_id', $serviceId)
            ->whereNull('deleted_at');

        if (is_string($templatePublicId) && trim($templatePublicId) !== '') {
            $template = $query->where('public_id', $templatePublicId)->first(['id', 'business_line_id', 'estimated_duration_minutes']);
            if (! $template) {
                throw new InvalidArgumentException('Job template does not belong to the selected service and active company.');
            }
            return $template;
        }

        return $query->where('status', 'active')->orderBy('id')->first(['id', 'business_line_id', 'estimated_duration_minutes']);
    }
}
