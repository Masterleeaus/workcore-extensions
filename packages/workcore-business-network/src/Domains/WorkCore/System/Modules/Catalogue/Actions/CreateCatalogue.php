<?php declare(strict_types=1);
namespace App\Domains\WorkCore\System\Modules\Catalogue\Actions;
final class CreateCatalogue {
    public function __construct(private readonly \App\Domains\WorkCore\System\Modules\Catalogue\Services\CatalogueService $service) {}
    public function execute(array $data): string { return $this->service->create($data); }
}
