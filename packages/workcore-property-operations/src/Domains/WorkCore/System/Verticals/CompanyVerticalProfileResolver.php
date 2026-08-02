<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Verticals;

use App\Domains\WorkCore\System\Models\CompanyFeature;
use App\Domains\WorkCore\System\Models\CompanyTerminologyOverride;
use App\Domains\WorkCore\System\Models\CompanyVertical;
use Illuminate\Support\Facades\Schema;
use Throwable;

final class CompanyVerticalProfileResolver
{
    /** @var array<int,CompiledVerticalProfile> */
    private array $resolved = [];

    public function __construct(private VerticalProfileCompiler $compiler) {}

    public function resolve(int $companyId): CompiledVerticalProfile
    {
        if (isset($this->resolved[$companyId])) {
            return $this->resolved[$companyId];
        }

        if (! (bool) config('workcore.verticals.enabled', true) || ! $this->tablesExist()) {
            return $this->resolved[$companyId] = $this->compiler->compile(null);
        }

        try {
            $selections = CompanyVertical::query()
                ->where('company_id', $companyId)
                ->where('status', 'active')
                ->orderByDesc('is_primary')
                ->orderBy('id')
                ->get();

            $primary = $selections->firstWhere('is_primary', true);
            if ($primary === null) {
                return $this->resolved[$companyId] = $this->compiler->compile(null);
            }

            $addOns = $selections
                ->reject(static fn (CompanyVertical $selection): bool => $selection->is_primary)
                ->pluck('vertical_key')
                ->map(static fn (mixed $key): string => (string) $key)
                ->values()
                ->all();

            $features = CompanyFeature::query()
                ->where('company_id', $companyId)
                ->where('scope_type', 'company')
                ->where('scope_id', $companyId)
                ->pluck('enabled', 'feature_key')
                ->map(static fn (mixed $enabled): bool => (bool) $enabled)
                ->all();

            $terms = CompanyTerminologyOverride::query()
                ->where('company_id', $companyId)
                ->where('scope_type', 'company')
                ->where('scope_id', $companyId)
                ->whereIn('locale', ['*', (string) config('app.locale', 'en')])
                ->orderByRaw("CASE WHEN locale = '*' THEN 0 ELSE 1 END")
                ->pluck('term_value', 'term_key')
                ->map(static fn (mixed $value): string => (string) $value)
                ->all();

            return $this->resolved[$companyId] = $this->compiler->compile(
                (string) $primary->vertical_key,
                $addOns,
                $features,
                $terms,
            );
        } catch (Throwable $exception) {
            if (! (bool) config('workcore.verticals.legacy_allow_when_unconfigured', true)) {
                throw $exception;
            }

            return $this->resolved[$companyId] = $this->compiler->compile(null);
        }
    }

    public function forget(?int $companyId = null): void
    {
        if ($companyId === null) {
            $this->resolved = [];
            return;
        }

        unset($this->resolved[$companyId]);
    }

    private function tablesExist(): bool
    {
        foreach (['tz_company_verticals', 'tz_company_features', 'tz_company_terminology_overrides'] as $table) {
            if (! Schema::hasTable($table)) {
                return false;
            }
        }

        return true;
    }
}
