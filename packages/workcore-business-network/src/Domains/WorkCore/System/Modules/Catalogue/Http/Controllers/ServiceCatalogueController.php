<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Catalogue\Http\Controllers;

use App\Domains\WorkCore\System\Actions\{ActionRequest, ActionResult, BusinessActionDispatcher};
use App\Domains\WorkCore\System\Api\ApiResponseFactory;
use App\Domains\WorkCore\System\Api\Resources\ServiceResource;
use App\Domains\WorkCore\System\Modules\Catalogue\Actions\{ListServiceFaqs, ListServiceVariants, SearchServices};
use App\Domains\WorkCore\System\Modules\Catalogue\Http\Requests\{
    StoreJobTemplateRequest,
    StoreServiceCategoryRequest,
    StoreServiceFaqRequest,
    StoreServiceRequest,
    StoreServiceVariantRequest,
    UpdateServiceFaqRequest,
    UpdateServiceVariantRequest
};
use Illuminate\Http\{JsonResponse, Request};

final class ServiceCatalogueController
{
    public function index(Request $request, SearchServices $search, ApiResponseFactory $responses): JsonResponse
    {
        return $responses->paginated(
            $search->execute(
                query: $request->string('q')->toString() ?: null,
                categoryPublicId: $request->string('category_id')->toString() ?: null,
                perPage: $request->integer('per_page', 25),
                businessLinePublicId: $request->string('business_line_id')->toString() ?: null,
            ),
            ServiceResource::make(...),
        );
    }

    public function listVariants(string $service, ListServiceVariants $list, ApiResponseFactory $responses): JsonResponse
    {
        return $responses->success($list->execute($service));
    }

    public function listFaqs(string $service, ListServiceFaqs $list, ApiResponseFactory $responses): JsonResponse
    {
        return $responses->success($list->execute($service));
    }

    public function storeCategory(StoreServiceCategoryRequest $request, BusinessActionDispatcher $dispatcher, ApiResponseFactory $responses): JsonResponse
    {
        return $this->dispatch('workcore.service_category.create', $request->validated(), $request, $dispatcher, $responses);
    }

    public function storeService(StoreServiceRequest $request, BusinessActionDispatcher $dispatcher, ApiResponseFactory $responses): JsonResponse
    {
        return $this->dispatch('workcore.service.create', $request->validated(), $request, $dispatcher, $responses);
    }

    public function storeTemplate(StoreJobTemplateRequest $request, BusinessActionDispatcher $dispatcher, ApiResponseFactory $responses): JsonResponse
    {
        return $this->dispatch('workcore.job_template.create', $request->validated(), $request, $dispatcher, $responses);
    }

    public function storeVariant(string $service, StoreServiceVariantRequest $request, BusinessActionDispatcher $dispatcher, ApiResponseFactory $responses): JsonResponse
    {
        return $this->dispatch('workcore.service_variant.create', ['service_public_id' => $service] + $request->validated(), $request, $dispatcher, $responses);
    }

    public function updateVariant(string $variant, UpdateServiceVariantRequest $request, BusinessActionDispatcher $dispatcher, ApiResponseFactory $responses): JsonResponse
    {
        return $this->dispatch('workcore.service_variant.update', ['variant_public_id' => $variant] + $request->validated(), $request, $dispatcher, $responses, 200);
    }

    public function deleteVariant(string $variant, Request $request, BusinessActionDispatcher $dispatcher, ApiResponseFactory $responses): JsonResponse
    {
        return $this->dispatch('workcore.service_variant.delete', ['variant_public_id' => $variant], $request, $dispatcher, $responses, 200);
    }

    public function storeFaq(string $service, StoreServiceFaqRequest $request, BusinessActionDispatcher $dispatcher, ApiResponseFactory $responses): JsonResponse
    {
        return $this->dispatch('workcore.service_faq.create', ['service_public_id' => $service] + $request->validated(), $request, $dispatcher, $responses);
    }

    public function updateFaq(string $faq, UpdateServiceFaqRequest $request, BusinessActionDispatcher $dispatcher, ApiResponseFactory $responses): JsonResponse
    {
        return $this->dispatch('workcore.service_faq.update', ['faq_public_id' => $faq] + $request->validated(), $request, $dispatcher, $responses, 200);
    }

    public function deleteFaq(string $faq, Request $request, BusinessActionDispatcher $dispatcher, ApiResponseFactory $responses): JsonResponse
    {
        return $this->dispatch('workcore.service_faq.delete', ['faq_public_id' => $faq], $request, $dispatcher, $responses, 200);
    }

    private function dispatch(string $key, array $payload, Request $request, BusinessActionDispatcher $dispatcher, ApiResponseFactory $responses, int $status = 201): JsonResponse
    {
        $tenant = workcore_tenant();
        $idempotencyKey = trim((string) $request->header('Idempotency-Key'));
        abort_if($idempotencyKey === '', 422, 'Idempotency-Key header is required.');
        $result = $dispatcher->dispatch(new ActionRequest(
            key: $key,
            payload: $payload,
            companyId: (int) $tenant->companyId(),
            actorId: (int) $tenant->userId(),
            idempotencyKey: $idempotencyKey,
            confirmationId: $request->header('X-WorkCore-Confirmation'),
            source: 'api',
        ));
        return $responses->action(new ActionResult($result->actionKey, $result->data, $result->correlationId, $result->auditId, $result->eventIds, $result->replayed), $status);
    }
}
