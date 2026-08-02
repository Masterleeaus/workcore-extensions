<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Verticals\ReadModels;

use App\Domains\WorkCore\System\Contracts\TenantContextContract;
use App\Domains\WorkCore\System\Models\{BusinessLine,CompanyDocumentTemplate,CompanyLocale,CompanyVertical};
use App\Domains\WorkCore\System\Verticals\Codebooks\CodebookResolver;
use App\Domains\WorkCore\System\Verticals\CompanyVerticalProfileResolver;

final class GetCompanyVerticalProfile
{
    public function __construct(
        private TenantContextContract $tenant,
        private CompanyVerticalProfileResolver $profiles,
        private CodebookResolver $codebooks,
    ) {}

    /** @return array<string,mixed> */
    public function execute(?int $companyId = null): array
    {
        $companyId ??= $this->tenant->companyId();
        $profile = $this->profiles->resolve($companyId);
        $codebooks = [];
        foreach (array_keys($profile->codebooks) as $key) {
            $codebooks[$key] = $this->codebooks->options($profile, (string) $key);
        }
        ksort($codebooks);

        return [
            'configured' => $profile->isConfigured(),
            'primary_vertical' => $profile->primaryVertical,
            'packs' => $profile->packKeys,
            'capabilities' => $profile->capabilities,
            'features' => $profile->features,
            'terminology' => $profile->terminology,
            'codebooks' => $codebooks,
            'workflows' => $profile->workflows,
            'selections' => CompanyVertical::query()->where('company_id', $companyId)->orderByDesc('is_primary')->get()->map(static fn (CompanyVertical $item): array => [
                'vertical_key' => $item->vertical_key,
                'pack_version' => $item->pack_version,
                'is_primary' => (bool) $item->is_primary,
                'status' => $item->status,
            ])->values()->all(),
            'business_lines' => BusinessLine::query()->where('company_id', $companyId)->orderByDesc('is_default')->orderBy('name')->get()->map(static fn (BusinessLine $line): array => [
                'public_id' => $line->public_id,
                'key' => $line->key,
                'name' => $line->name,
                'is_default' => (bool) $line->is_default,
                'status' => $line->status,
                'settings' => (array) ($line->settings ?? []),
            ])->values()->all(),
            'locales' => CompanyLocale::query()->where('company_id', $companyId)->where('is_active', true)->orderByDesc('is_default')->get()->map(static fn (CompanyLocale $locale): array => [
                'locale' => $locale->locale,
                'display_name' => $locale->display_name,
                'direction' => $locale->direction,
                'is_default' => (bool) $locale->is_default,
            ])->values()->all(),
            'document_template_count' => CompanyDocumentTemplate::query()->where('company_id', $companyId)->where('is_active', true)->count(),
        ];
    }
}
