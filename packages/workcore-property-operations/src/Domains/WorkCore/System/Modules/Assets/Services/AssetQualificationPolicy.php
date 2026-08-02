<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Assets\Services;

use Illuminate\Database\ConnectionInterface;
use InvalidArgumentException;

final class AssetQualificationPolicy
{
    public function __construct(private ConnectionInterface $db) {}

    /** @param array<string,mixed> $requirements */
    public function assertQualified(int $companyId, int $workerId, ?int $categoryId, array $requirements = []): void
    {
        $categoryRequirements = $this->categoryRequirements($companyId, $categoryId);
        $rules = array_replace_recursive($categoryRequirements, $requirements);

        $requiredSkillPublicIds = $this->strings($rules['required_skill_public_ids'] ?? []);
        $requiredSkillCodes = $this->strings($rules['required_skill_codes'] ?? []);
        $requiredCertificationPublicIds = $this->strings($rules['required_certification_public_ids'] ?? []);
        $requireVerified = (bool) ($rules['require_verified_skills'] ?? true);
        $requireUnexpired = (bool) ($rules['require_unexpired_certifications'] ?? true);

        if ($requiredSkillPublicIds !== [] || $requiredSkillCodes !== []) {
            $skills = $this->db->table('tz_worker_skills as worker_skills')
                ->join('tz_skills as skills', 'skills.id', '=', 'worker_skills.skill_id')
                ->where('worker_skills.company_id', $companyId)
                ->where('worker_skills.worker_id', $workerId)
                ->where('skills.company_id', $companyId)
                ->whereNull('skills.deleted_at')
                ->where('skills.active', true)
                ->when($requireVerified, static fn ($query) => $query->where('worker_skills.verified', true))
                ->get(['skills.id', 'skills.public_id', 'skills.code', 'skills.requires_certification']);

            $heldPublicIds = $skills->pluck('public_id')->map(static fn ($value): string => (string) $value)->all();
            $heldCodes = $skills->pluck('code')->map(static fn ($value): string => (string) $value)->all();
            $missingPublicIds = array_values(array_diff($requiredSkillPublicIds, $heldPublicIds));
            $missingCodes = array_values(array_diff($requiredSkillCodes, $heldCodes));
            if ($missingPublicIds !== [] || $missingCodes !== []) {
                throw new InvalidArgumentException('Worker lacks required asset skills: ' . implode(', ', array_merge($missingPublicIds, $missingCodes)) . '.');
            }
            $certifiedSkillIds = $skills->filter(static function ($skill) use ($requiredSkillPublicIds, $requiredSkillCodes): bool {
                return (bool) $skill->requires_certification
                    && (in_array((string) $skill->public_id, $requiredSkillPublicIds, true)
                        || in_array((string) $skill->code, $requiredSkillCodes, true));
            })->pluck('id')->map(static fn ($value): int => (int) $value)->all();
            if ($certifiedSkillIds !== []) {
                $certificationQuery = $this->db->table('tz_worker_certifications')
                    ->where('company_id', $companyId)
                    ->where('worker_id', $workerId)
                    ->whereIn('skill_id', $certifiedSkillIds)
                    ->where('status', 'valid')
                    ->whereNull('deleted_at');
                if ($requireUnexpired) {
                    $certificationQuery->where(static function ($builder): void {
                        $builder->whereNull('expires_on')->orWhereDate('expires_on', '>=', now()->toDateString());
                    });
                }
                $certified = $certificationQuery->pluck('skill_id')->map(static fn ($value): int => (int) $value)->all();
                $missingCertificationSkills = array_values(array_diff($certifiedSkillIds, $certified));
                if ($missingCertificationSkills !== []) {
                    throw new InvalidArgumentException('Worker lacks certification required by an asset skill.');
                }
            }
        }

        if ($requiredCertificationPublicIds !== []) {
            $query = $this->db->table('tz_worker_certifications')
                ->where('company_id', $companyId)
                ->where('worker_id', $workerId)
                ->whereNull('deleted_at')
                ->whereIn('public_id', $requiredCertificationPublicIds)
                ->where('status', 'valid');
            if ($requireUnexpired) {
                $query->where(static function ($builder): void {
                    $builder->whereNull('expires_on')->orWhereDate('expires_on', '>=', now()->toDateString());
                });
            }
            $held = $query->pluck('public_id')->map(static fn ($value): string => (string) $value)->all();
            $missing = array_values(array_diff($requiredCertificationPublicIds, $held));
            if ($missing !== []) {
                throw new InvalidArgumentException('Worker lacks valid asset certifications: ' . implode(', ', $missing) . '.');
            }
        }
    }

    /** @return array<string,mixed> */
    private function categoryRequirements(int $companyId, ?int $categoryId): array
    {
        if ($categoryId === null) {
            return [];
        }
        $settings = $this->db->table('tz_asset_categories')
            ->where('company_id', $companyId)
            ->where('id', $categoryId)
            ->whereNull('deleted_at')
            ->value('settings');
        if (is_array($settings)) {
            $decoded = $settings;
        } elseif (is_string($settings) && trim($settings) !== '') {
            $decoded = json_decode($settings, true);
        } else {
            return [];
        }
        if (! is_array($decoded)) {
            return [];
        }
        $requirements = $decoded['credential_requirements'] ?? $decoded['equipment_qualification'] ?? [];
        return is_array($requirements) ? $requirements : [];
    }

    /** @return array<int,string> */
    private function strings(mixed $values): array
    {
        if (! is_array($values)) {
            return [];
        }
        return array_values(array_unique(array_filter(array_map(
            static fn ($value): string => trim((string) $value),
            $values,
        ), static fn (string $value): bool => $value !== '')));
    }
}
