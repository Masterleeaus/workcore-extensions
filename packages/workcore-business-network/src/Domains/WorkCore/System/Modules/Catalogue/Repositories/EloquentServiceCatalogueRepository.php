<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Catalogue\Repositories;

use App\Domains\WorkCore\System\Modules\Catalogue\Contracts\ServiceCatalogueRepositoryContract;
use App\Domains\WorkCore\System\Persistence\TenantScopedRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class EloquentServiceCatalogueRepository extends TenantScopedRepository implements ServiceCatalogueRepositoryContract
{
    public function searchServices(
        int $companyId,
        ?string $query = null,
        ?string $categoryPublicId = null,
        int $perPage = 25,
        ?string $businessLinePublicId = null,
    ): LengthAwarePaginator {
        $this->assertCompany($companyId);
        $builder = $this->db->table('tz_services as services')
            ->leftJoin('tz_service_categories as categories', function ($join): void {
                $join->on('categories.id', '=', 'services.category_id')
                    ->on('categories.company_id', '=', 'services.company_id');
            })
            ->leftJoin('tz_business_lines as bl', function ($join): void {
                $join->on('bl.id', '=', 'services.business_line_id')
                    ->on('bl.company_id', '=', 'services.company_id')
                    ->whereNull('bl.deleted_at');
            })
            ->where('services.company_id', $companyId)
            ->whereNull('services.deleted_at');
        $builder->when($query, fn ($queryBuilder) => $queryBuilder->where(fn ($nested) => $nested
            ->where('services.name', 'like', "%{$query}%")
            ->orWhere('services.code', 'like', "%{$query}%")
            ->orWhere('services.description', 'like', "%{$query}%")));
        $builder->when($categoryPublicId, fn ($queryBuilder) => $queryBuilder->where('categories.public_id', $categoryPublicId));
        $builder->when($businessLinePublicId, fn ($queryBuilder) => $queryBuilder->where('bl.public_id', $businessLinePublicId));

        return $builder->select([
            'services.public_id',
            'bl.public_id as business_line_public_id',
            'bl.name as business_line_name',
            'categories.public_id as category_public_id',
            'categories.name as category_name',
            'services.code',
            'services.name',
            'services.description',
            'services.default_duration_minutes',
            'services.pricing_model',
            'services.base_price',
            'services.tax_code',
            'services.status',
        ])->orderBy('services.name')->paginate(max(1, min($perPage, 100)));
    }

    public function createCategory(array $data, int $companyId, int $actorId): array
    {
        $this->guard($companyId, $actorId);
        $businessLineId = $this->resolveBusinessLineId($companyId, $data['business_line_public_id'] ?? null);
        $publicId = (string) Str::ulid();
        $this->db->table('tz_service_categories')->insert([
            'public_id' => $publicId,
            'company_id' => $companyId,
            'business_line_id' => $businessLineId,
            'parent_id' => null,
            'name' => trim((string) $data['name']),
            'description' => $data['description'] ?? null,
            'sort_order' => $data['sort_order'] ?? 0,
            'status' => 'active',
            'created_by_user_id' => $actorId,
            'updated_by_user_id' => $actorId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        return (array) $this->db->table('tz_service_categories')->where('company_id', $companyId)->where('public_id', $publicId)->first();
    }

    public function createService(array $data, int $companyId, int $actorId): array
    {
        $this->guard($companyId, $actorId);
        $businessLineId = $this->resolveBusinessLineId($companyId, $data['business_line_public_id'] ?? null);
        $categoryId = null;
        if (! empty($data['category_public_id'])) {
            $category = $this->db->table('tz_service_categories')
                ->where('company_id', $companyId)
                ->where('public_id', $data['category_public_id'])
                ->whereNull('deleted_at')
                ->first(['id', 'business_line_id']);
            if ($category === null) {
                throw new InvalidArgumentException('Service category does not belong to the active company.');
            }
            $categoryId = (int) $category->id;
            $categoryBusinessLineId = $category->business_line_id === null ? null : (int) $category->business_line_id;
            $businessLineId = $this->inheritBusinessLine($businessLineId, $categoryBusinessLineId, 'service category');
        }
        $publicId = (string) Str::ulid();
        $this->db->table('tz_services')->insert([
            'public_id' => $publicId,
            'company_id' => $companyId,
            'business_line_id' => $businessLineId,
            'category_id' => $categoryId,
            'code' => $data['code'] ?? null,
            'name' => trim((string) $data['name']),
            'description' => $data['description'] ?? null,
            'default_duration_minutes' => $data['default_duration_minutes'] ?? null,
            'pricing_model' => $data['pricing_model'] ?? 'fixed',
            'base_price' => $data['base_price'] ?? null,
            'tax_code' => $data['tax_code'] ?? null,
            'status' => 'active',
            'created_by_user_id' => $actorId,
            'updated_by_user_id' => $actorId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        return (array) $this->db->table('tz_services')->where('company_id', $companyId)->where('public_id', $publicId)->first();
    }

    public function createJobTemplate(array $data, int $companyId, int $actorId): array
    {
        $this->guard($companyId, $actorId);
        $service = $this->service($companyId, (string) $data['service_public_id']);
        $businessLineId = $this->resolveBusinessLineId($companyId, $data['business_line_public_id'] ?? null);
        $serviceBusinessLineId = $service->business_line_id === null ? null : (int) $service->business_line_id;
        $businessLineId = $this->inheritBusinessLine($businessLineId, $serviceBusinessLineId, 'service');

        return $this->db->transaction(function () use ($data, $companyId, $actorId, $service, $businessLineId): array {
            $publicId = (string) Str::ulid();
            $templateId = $this->db->table('tz_job_templates')->insertGetId([
                'public_id' => $publicId,
                'company_id' => $companyId,
                'business_line_id' => $businessLineId,
                'service_id' => $service->id,
                'name' => trim((string) $data['name']),
                'description' => $data['description'] ?? null,
                'estimated_duration_minutes' => $data['estimated_duration_minutes'] ?? null,
                'status' => 'active',
                'created_by_user_id' => $actorId,
                'updated_by_user_id' => $actorId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            foreach (($data['tasks'] ?? []) as $index => $task) {
                $this->db->table('tz_task_templates')->insert([
                    'public_id' => (string) Str::ulid(),
                    'company_id' => $companyId,
                    'job_template_id' => $templateId,
                    'name' => trim((string) $task['name']),
                    'description' => $task['description'] ?? null,
                    'sort_order' => $task['sort_order'] ?? $index,
                    'estimated_minutes' => $task['estimated_minutes'] ?? null,
                    'is_required' => $task['is_required'] ?? true,
                    'active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            return [
                'public_id' => $publicId,
                'business_line_public_id' => $data['business_line_public_id'] ?? null,
                'service_public_id' => $data['service_public_id'],
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'estimated_duration_minutes' => $data['estimated_duration_minutes'] ?? null,
                'tasks' => $data['tasks'] ?? [],
                'status' => 'active',
            ];
        });
    }

    public function listVariants(string $servicePublicId, int $companyId): array
    {
        $this->assertCompany($companyId);
        $service = $this->service($companyId, $servicePublicId);
        return $this->db->table('tz_service_variants')
            ->where('company_id', $companyId)->where('service_id', $service->id)->whereNull('deleted_at')
            ->orderBy('sort_order')->orderBy('name')
            ->get(['public_id', 'key', 'name', 'description', 'default_duration_minutes', 'pricing_model', 'base_price', 'status', 'sort_order'])
            ->map(static fn ($row): array => (array) $row)->all();
    }

    public function createVariant(array $data, int $companyId, int $actorId): array
    {
        $this->guard($companyId, $actorId);
        $service = $this->service($companyId, (string) $data['service_public_id']);
        $publicId = (string) Str::ulid();
        $this->db->table('tz_service_variants')->insert([
            'public_id' => $publicId, 'company_id' => $companyId, 'service_id' => $service->id,
            'key' => $this->variantKey((string) $data['key']), 'name' => trim((string) $data['name']),
            'description' => $data['description'] ?? null, 'default_duration_minutes' => $data['default_duration_minutes'] ?? null,
            'pricing_model' => $data['pricing_model'] ?? 'fixed', 'base_price' => $data['base_price'] ?? null,
            'status' => $data['status'] ?? 'active', 'sort_order' => (int) ($data['sort_order'] ?? 0),
            'created_by_user_id' => $actorId, 'updated_by_user_id' => $actorId, 'created_at' => now(), 'updated_at' => now(),
        ]);
        return $this->variant($companyId, $publicId);
    }

    public function updateVariant(string $variantPublicId, array $data, int $companyId, int $actorId): array
    {
        $this->guard($companyId, $actorId);
        $variant = $this->variantRecord($companyId, $variantPublicId);
        $allowed = array_intersect_key($data, array_flip(['key', 'name', 'description', 'default_duration_minutes', 'pricing_model', 'base_price', 'status', 'sort_order']));
        if (isset($allowed['key'])) { $allowed['key'] = $this->variantKey((string) $allowed['key']); }
        if (isset($allowed['name'])) { $allowed['name'] = trim((string) $allowed['name']); }
        $allowed['updated_by_user_id'] = $actorId; $allowed['updated_at'] = now();
        $this->db->table('tz_service_variants')->where('company_id', $companyId)->where('id', $variant->id)->update($allowed);
        return $this->variant($companyId, $variantPublicId);
    }

    public function deleteVariant(string $variantPublicId, int $companyId, int $actorId): array
    {
        $this->guard($companyId, $actorId);
        $variant = $this->variantRecord($companyId, $variantPublicId);
        $this->db->table('tz_service_variants')->where('company_id', $companyId)->where('id', $variant->id)->update(['deleted_at' => now(), 'updated_by_user_id' => $actorId, 'updated_at' => now()]);
        return ['public_id' => $variantPublicId, 'status' => 'deleted'];
    }

    public function listFaqs(string $servicePublicId, int $companyId): array
    {
        $this->assertCompany($companyId);
        $service = $this->service($companyId, $servicePublicId);
        return $this->db->table('tz_service_faqs')
            ->where('company_id', $companyId)->where('service_id', $service->id)->whereNull('deleted_at')
            ->orderBy('sort_order')->orderBy('id')
            ->get(['public_id', 'question', 'answer', 'status', 'sort_order'])
            ->map(static fn ($row): array => (array) $row)->all();
    }

    public function createFaq(array $data, int $companyId, int $actorId): array
    {
        $this->guard($companyId, $actorId);
        $service = $this->service($companyId, (string) $data['service_public_id']);
        $publicId = (string) Str::ulid();
        $this->db->table('tz_service_faqs')->insert([
            'public_id' => $publicId, 'company_id' => $companyId, 'service_id' => $service->id,
            'question' => trim((string) $data['question']), 'answer' => trim((string) $data['answer']),
            'status' => $data['status'] ?? 'active', 'sort_order' => (int) ($data['sort_order'] ?? 0),
            'created_by_user_id' => $actorId, 'updated_by_user_id' => $actorId, 'created_at' => now(), 'updated_at' => now(),
        ]);
        return $this->faq($companyId, $publicId);
    }

    public function updateFaq(string $faqPublicId, array $data, int $companyId, int $actorId): array
    {
        $this->guard($companyId, $actorId);
        $faq = $this->faqRecord($companyId, $faqPublicId);
        $allowed = array_intersect_key($data, array_flip(['question', 'answer', 'status', 'sort_order']));
        if (isset($allowed['question'])) { $allowed['question'] = trim((string) $allowed['question']); }
        if (isset($allowed['answer'])) { $allowed['answer'] = trim((string) $allowed['answer']); }
        $allowed['updated_by_user_id'] = $actorId; $allowed['updated_at'] = now();
        $this->db->table('tz_service_faqs')->where('company_id', $companyId)->where('id', $faq->id)->update($allowed);
        return $this->faq($companyId, $faqPublicId);
    }

    public function deleteFaq(string $faqPublicId, int $companyId, int $actorId): array
    {
        $this->guard($companyId, $actorId);
        $faq = $this->faqRecord($companyId, $faqPublicId);
        $this->db->table('tz_service_faqs')->where('company_id', $companyId)->where('id', $faq->id)->update(['deleted_at' => now(), 'updated_by_user_id' => $actorId, 'updated_at' => now()]);
        return ['public_id' => $faqPublicId, 'status' => 'deleted'];
    }

    private function service(int $companyId, string $publicId): object
    {
        $record = $this->db->table('tz_services')->where('company_id', $companyId)->where('public_id', $publicId)->whereNull('deleted_at')->first(['id', 'public_id', 'business_line_id']);
        if (! $record) { throw new InvalidArgumentException('Service does not belong to the active company.'); }
        return $record;
    }

    private function variantRecord(int $companyId, string $publicId): object
    {
        $record = $this->db->table('tz_service_variants')->where('company_id', $companyId)->where('public_id', $publicId)->whereNull('deleted_at')->first(['id']);
        if (! $record) { throw new InvalidArgumentException('Service variant does not belong to the active company.'); }
        return $record;
    }

    private function faqRecord(int $companyId, string $publicId): object
    {
        $record = $this->db->table('tz_service_faqs')->where('company_id', $companyId)->where('public_id', $publicId)->whereNull('deleted_at')->first(['id']);
        if (! $record) { throw new InvalidArgumentException('Service FAQ does not belong to the active company.'); }
        return $record;
    }

    private function variant(int $companyId, string $publicId): array
    {
        return (array) $this->db->table('tz_service_variants as variants')->join('tz_services as services', function ($join): void {
            $join->on('services.id', '=', 'variants.service_id')->on('services.company_id', '=', 'variants.company_id');
        })->where('variants.company_id', $companyId)->where('variants.public_id', $publicId)->whereNull('variants.deleted_at')
            ->first(['variants.public_id', 'services.public_id as service_public_id', 'variants.key', 'variants.name', 'variants.description', 'variants.default_duration_minutes', 'variants.pricing_model', 'variants.base_price', 'variants.status', 'variants.sort_order']);
    }

    private function faq(int $companyId, string $publicId): array
    {
        return (array) $this->db->table('tz_service_faqs as faqs')->join('tz_services as services', function ($join): void {
            $join->on('services.id', '=', 'faqs.service_id')->on('services.company_id', '=', 'faqs.company_id');
        })->where('faqs.company_id', $companyId)->where('faqs.public_id', $publicId)->whereNull('faqs.deleted_at')
            ->first(['faqs.public_id', 'services.public_id as service_public_id', 'faqs.question', 'faqs.answer', 'faqs.status', 'faqs.sort_order']);
    }

    private function variantKey(string $key): string
    {
        $key = trim($key);
        if ($key === '' || preg_match('/^[A-Za-z0-9][A-Za-z0-9_-]*$/', $key) !== 1) {
            throw new InvalidArgumentException('Variant key must contain only letters, numbers, underscores and hyphens.');
        }
        return strtolower($key);
    }

    private function resolveBusinessLineId(int $companyId, mixed $publicId): ?int
    {
        $publicId = trim((string) $publicId);
        if ($publicId === '') {
            return null;
        }
        $id = $this->db->table('tz_business_lines')
            ->where('company_id', $companyId)
            ->where('public_id', $publicId)
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->value('id');
        if ($id === null) {
            throw new InvalidArgumentException('Business line does not belong to the active company or is inactive.');
        }
        return (int) $id;
    }

    private function inheritBusinessLine(?int $requestedId, ?int $parentId, string $parentLabel): ?int
    {
        if ($requestedId !== null && $parentId !== null && $requestedId !== $parentId) {
            throw new InvalidArgumentException("Business line must match the selected {$parentLabel}.");
        }
        return $requestedId ?? $parentId;
    }

    private function guard(int $companyId, int $actorId): void
    {
        $this->assertCompany($companyId);
        if ($actorId !== $this->actorId()) { throw new \RuntimeException('Repository actor does not match the active WorkCore actor.'); }
    }
}
