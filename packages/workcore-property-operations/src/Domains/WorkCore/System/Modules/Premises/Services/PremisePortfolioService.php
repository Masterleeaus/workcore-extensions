<?php

namespace App\Domains\WorkCore\System\Modules\Premises\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use App\Domains\WorkCore\System\Modules\Premises\Entities\PremisePortfolio;
use App\Domains\WorkCore\System\Modules\Premises\Entities\PremisePortfolioMembership;
use App\Domains\WorkCore\System\Modules\Premises\Entities\Property;
use App\Domains\WorkCore\System\Modules\Premises\Events\PremisePortfolioChanged;

class PremisePortfolioService
{
    public function create(int $companyId, array $attributes): PremisePortfolio
    {
        $parentId = $attributes['parent_portfolio_id'] ?? null;
        if ($parentId) {
            $parent = PremisePortfolio::withoutGlobalScopes()->where('company_id', $companyId)->findOrFail($parentId);
            if ($parent->status !== 'active') throw ValidationException::withMessages(['parent_portfolio_id' => 'Archived portfolios cannot receive child portfolios.']);
        }
        $portfolio = PremisePortfolio::create([
            'company_id' => $companyId,
            'parent_portfolio_id' => $parentId,
            'code' => $attributes['code'] ?? null,
            'name' => $attributes['name'],
            'description' => $attributes['description'] ?? null,
            'status' => 'active',
            'external_reference' => $attributes['external_reference'] ?? null,
            'metadata' => $attributes['metadata'] ?? null,
        ]);
        event(new PremisePortfolioChanged($portfolio, 'created'));
        return $portfolio;
    }

    public function update(PremisePortfolio $portfolio, array $attributes): PremisePortfolio
    {
        if (!empty($attributes['parent_portfolio_id'])) $this->assertNoCycle($portfolio, (int) $attributes['parent_portfolio_id']);
        $portfolio->update(array_intersect_key($attributes, array_flip(['parent_portfolio_id', 'code', 'name', 'description', 'external_reference', 'metadata'])));
        event(new PremisePortfolioChanged($portfolio, 'updated'));
        return $portfolio->fresh();
    }

    public function addPremise(PremisePortfolio $portfolio, Property $premise, string $type = 'primary', ?string $validFrom = null): PremisePortfolioMembership
    {
        if ((int) $portfolio->company_id !== (int) $premise->company_id) throw ValidationException::withMessages(['premise_id' => 'Portfolio and premise must belong to the same company.']);
        if ($portfolio->status !== 'active') throw ValidationException::withMessages(['portfolio' => 'Archived portfolios cannot receive premises.']);
        if (!in_array($type, PremisePortfolioMembership::TYPES, true)) throw ValidationException::withMessages(['membership_type' => 'Unsupported portfolio membership type.']);

        return DB::transaction(function () use ($portfolio, $premise, $type, $validFrom) {
            if ($type === 'primary') {
                PremisePortfolioMembership::withoutGlobalScopes()->where('company_id', $premise->company_id)
                    ->where('premise_id', $premise->id)->where('membership_type', 'primary')->whereNull('valid_until')
                    ->get()->each(fn ($membership) => $membership->update(['valid_until' => today()->toDateString()]));
            }
            $membership = PremisePortfolioMembership::create([
                'company_id' => $premise->company_id,
                'portfolio_id' => $portfolio->id,
                'premise_id' => $premise->id,
                'membership_type' => $type,
                'valid_from' => $validFrom ?: today()->toDateString(),
            ]);
            event(new PremisePortfolioChanged($portfolio, 'premise_added'));
            return $membership;
        });
    }

    public function endMembership(PremisePortfolioMembership $membership, ?string $date = null): PremisePortfolioMembership
    {
        if ($membership->valid_until) throw ValidationException::withMessages(['membership' => 'This portfolio membership has already ended.']);
        $membership->update(['valid_until' => $date ?: today()->toDateString()]);
        event(new PremisePortfolioChanged($membership->portfolio, 'premise_removed'));
        return $membership->fresh();
    }

    public function archive(PremisePortfolio $portfolio): PremisePortfolio
    {
        $hasActiveChildren = $portfolio->children()->where('status', 'active')->exists();
        if ($hasActiveChildren) throw ValidationException::withMessages(['portfolio' => 'Archive or move active child portfolios first.']);
        $portfolio->update(['status' => 'archived']);
        event(new PremisePortfolioChanged($portfolio, 'archived'));
        return $portfolio->fresh();
    }

    private function assertNoCycle(PremisePortfolio $portfolio, int $parentId): void
    {
        if ($parentId === (int) $portfolio->id) throw ValidationException::withMessages(['parent_portfolio_id' => 'A portfolio cannot be its own parent.']);
        $cursor = PremisePortfolio::withoutGlobalScopes()->where('company_id', $portfolio->company_id)->findOrFail($parentId);
        while ($cursor) {
            if ((int) $cursor->id === (int) $portfolio->id) throw ValidationException::withMessages(['parent_portfolio_id' => 'Portfolio hierarchy cannot contain a cycle.']);
            $cursor = $cursor->parent_portfolio_id ? PremisePortfolio::withoutGlobalScopes()->find($cursor->parent_portfolio_id) : null;
        }
    }
}
