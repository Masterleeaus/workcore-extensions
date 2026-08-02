<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Verticals\Codebooks;

use App\Domains\WorkCore\System\Contracts\TenantContextContract;
use App\Domains\WorkCore\System\Verticals\CompanyVerticalProfileResolver;
use Illuminate\Validation\Rule;
use Throwable;

final class VerticalValidationRules
{
    public function __construct(
        private TenantContextContract $tenant,
        private CompanyVerticalProfileResolver $profiles,
        private CodebookResolver $codebooks,
    ) {}

    /** @param list<string> $fallback @param list<string>|null $allowedSubset @return list<string> */
    public function values(string $key, array $fallback, ?array $allowedSubset = null): array
    {
        $fallback = $this->normalise($fallback);
        if (! $this->tenant->hasTenant()) {
            return $this->limit($fallback, $allowedSubset);
        }

        try {
            $profile = $this->profiles->resolve($this->tenant->companyId());
            if (! $profile->isConfigured()) {
                return $this->limit($fallback, $allowedSubset);
            }
            $resolved = $this->limit($this->codebooks->values($profile, $key), $allowedSubset);
            return $resolved !== [] ? $resolved : $this->limit($fallback, $allowedSubset);
        } catch (Throwable $exception) {
            if (! (bool) config('workcore.verticals.legacy_allow_when_unconfigured', true)) {
                throw $exception;
            }
            return $this->limit($fallback, $allowedSubset);
        }
    }

    /** @param list<string> $fallback @param list<string>|null $allowedSubset */
    public function rule(string $key, array $fallback, ?array $allowedSubset = null): object
    {
        return Rule::in($this->values($key, $fallback, $allowedSubset));
    }

    /** @param list<string> $values @return list<string> */
    private function normalise(array $values): array
    {
        return array_values(array_unique(array_filter(
            array_map('strval', $values),
            static fn (string $value): bool => $value !== '',
        )));
    }

    /** @param list<string> $values @param list<string>|null $allowedSubset @return list<string> */
    private function limit(array $values, ?array $allowedSubset): array
    {
        $values = $this->normalise($values);
        if ($allowedSubset === null) {
            return $values;
        }
        $allowed = array_fill_keys($this->normalise($allowedSubset), true);
        return array_values(array_filter($values, static fn (string $value): bool => isset($allowed[$value])));
    }
}
