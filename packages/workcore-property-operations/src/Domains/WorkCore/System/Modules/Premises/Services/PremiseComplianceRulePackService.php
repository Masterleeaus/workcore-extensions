<?php

namespace App\Domains\WorkCore\System\Modules\Premises\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use App\Domains\WorkCore\System\Modules\Premises\Entities\PremiseComplianceRequirement;
use App\Domains\WorkCore\System\Modules\Premises\Entities\PremiseComplianceRulePack;
use App\Domains\WorkCore\System\Modules\Premises\Entities\Property;
use App\Domains\WorkCore\System\Modules\Premises\Events\PremiseComplianceRulePackApplied;

class PremiseComplianceRulePackService
{
    public function __construct(
        private ComplianceRulePackRegistry $registry,
        private PremiseComplianceService $compliance
    ) {}

    public function installBundled(string $key, int $companyId, ?int $actorId = null): PremiseComplianceRulePack
    {
        $definition = $this->registry->get($key);
        $identity = ['company_id' => $companyId, 'key' => $key, 'version' => $definition['version'] ?? '1.0.0'];
        $pack = PremiseComplianceRulePack::withoutGlobalScopes()->firstOrNew($identity);
        $isNew = !$pack->exists;
        $pack->fill([
            'user_id' => $pack->user_id ?: ($actorId ?? $this->actorId()),
            'name' => $definition['name'],
            'description' => $definition['description'] ?? null,
            'profile_key' => $definition['profile_key'] ?? null,
            'jurisdiction_country' => $definition['jurisdiction_country'] ?? null,
            'jurisdiction_region' => $definition['jurisdiction_region'] ?? null,
            'requires_local_verification' => (bool) ($definition['requires_local_verification'] ?? true),
            'disclaimer' => $definition['disclaimer'] ?? $this->registry->disclaimer(),
            'template_requirements' => $definition['requirements'] ?? [],
            'metadata' => array_merge($pack->metadata ?? [], ['bundled' => true, 'installed_from' => 'Config/compliance_rule_packs.php']),
        ]);
        if ($isNew) {
            $pack->status = 'draft';
            $pack->verification_status = 'unverified';
        }
        $pack->save();
        return $pack->fresh();
    }

    public function createCustom(int $companyId, array $attributes): PremiseComplianceRulePack
    {
        $key = !empty($attributes['key']) ? $attributes['key'] : Str::slug($attributes['name'], '_');
        return PremiseComplianceRulePack::create(array_merge($attributes, [
            'company_id' => $companyId,
            'user_id' => $attributes['user_id'] ?? $this->actorId(),
            'key' => $key,
            'status' => 'draft',
            'verification_status' => 'unverified',
            'requires_local_verification' => true,
            'disclaimer' => $attributes['disclaimer'] ?? $this->registry->disclaimer(),
        ]));
    }

    public function review(PremiseComplianceRulePack $pack, array $attributes): PremiseComplianceRulePack
    {
        $status = $attributes['verification_status'];
        if (!in_array($status, ['reviewed', 'verified', 'rejected'], true)) {
            throw ValidationException::withMessages(['verification_status' => 'Unsupported review result.']);
        }
        if (array_key_exists('source_title', $attributes) || array_key_exists('source_url', $attributes)) {
            $pack->fill([
                'source_title' => $attributes['source_title'] ?? $pack->source_title,
                'source_url' => $attributes['source_url'] ?? $pack->source_url,
            ]);
        }
        if ($status === 'verified') {
            if (empty($attributes['confirmed_by_reviewer'])) {
                throw ValidationException::withMessages(['confirmed_by_reviewer' => 'Explicit reviewer confirmation is required.']);
            }
            if (empty($pack->source_title) || empty($pack->source_url)) {
                throw ValidationException::withMessages(['source_url' => 'Verified packs require a source title and source URL.']);
            }
        }

        $pack->save();
        $pack->update([
            'verification_status' => $status,
            'verified_at' => $status === 'verified' ? now() : null,
            'verified_by_user_id' => $status === 'verified' ? $this->actorId() : null,
            'status' => $status === 'verified' ? 'active' : $pack->status,
            'metadata' => array_merge($pack->metadata ?? [], [
                'review_notes' => $attributes['review_notes'] ?? null,
                'reviewed_at' => now()->toIso8601String(),
                'reviewed_by_user_id' => $this->actorId(),
            ]),
        ]);
        return $pack->fresh();
    }

    public function retire(PremiseComplianceRulePack $pack): PremiseComplianceRulePack
    {
        $pack->update(['status' => 'retired']);
        return $pack->fresh();
    }

    public function applyToPremise(PremiseComplianceRulePack $pack, Property $premise, bool $acknowledgeUnverified = false): int
    {
        if ((int) $pack->company_id !== (int) $premise->company_id) {
            throw ValidationException::withMessages(['rule_pack' => 'Rule pack and premise belong to different companies.']);
        }
        if ($pack->status === 'retired') {
            throw ValidationException::withMessages(['rule_pack' => 'Retired rule packs cannot be applied.']);
        }
        if ($pack->profile_key && $pack->profile_key !== $premise->profileKey()) {
            throw ValidationException::withMessages(['rule_pack' => 'This rule pack targets a different premises profile.']);
        }
        if (!$pack->isVerified() && !$acknowledgeUnverified) {
            throw ValidationException::withMessages(['acknowledge_unverified' => 'Acknowledge that this rule pack has not been legally verified for the premise.']);
        }

        return DB::transaction(function () use ($pack, $premise) {
            $created = 0;
            foreach ($pack->template_requirements ?? [] as $template) {
                $this->validateTemplate($template);
                $packKey = (string) ($template['key'] ?? Str::slug($template['title'] ?? 'requirement', '_'));
                $exists = PremiseComplianceRequirement::withoutGlobalScopes()
                    ->where('company_id', $premise->company_id)
                    ->where('premise_id', $premise->id)
                    ->where('rule_pack_id', $pack->id)
                    ->where('pack_requirement_key', $packKey)
                    ->exists();
                if ($exists) {
                    continue;
                }

                $initialDueAt = now()->addDays(max(0, (int) ($template['initial_due_offset_days'] ?? 30)));
                $this->compliance->createRequirement($premise, [
                    'rule_pack_id' => $pack->id,
                    'pack_requirement_key' => $packKey,
                    'requirement_type' => $template['requirement_type'] ?? 'custom',
                    'title' => $template['title'] ?? Str::headline($packKey),
                    'description' => $template['description'] ?? null,
                    'jurisdiction_country' => $pack->jurisdiction_country,
                    'jurisdiction_region' => $pack->jurisdiction_region,
                    'jurisdiction_locality' => $pack->jurisdiction_locality,
                    'source_title' => $pack->source_title,
                    'source_url' => $pack->source_url,
                    'source_verification_status' => $pack->verification_status,
                    'source_verified_at' => $pack->verified_at,
                    'source_verified_by_user_id' => $pack->verified_by_user_id,
                    'recurrence_type' => $template['recurrence_type'] ?? 'none',
                    'recurrence_interval' => $template['recurrence_interval'] ?? null,
                    'annual_month' => $template['annual_month'] ?? null,
                    'annual_day' => $template['annual_day'] ?? null,
                    'initial_due_at' => $initialDueAt,
                    'warning_days' => $template['warning_days'] ?? 30,
                    'grace_days' => $template['grace_days'] ?? 0,
                    'severity' => $template['severity'] ?? 'medium',
                    'status' => 'active',
                    'evidence_required' => (bool) ($template['evidence_required'] ?? true),
                    'evidence_types' => $template['evidence_types'] ?? null,
                    'metadata' => [
                        'rule_pack_version' => $pack->version,
                        'requires_local_verification' => (bool) $pack->requires_local_verification,
                        'rule_pack_disclaimer' => $pack->disclaimer,
                    ],
                ]);
                $created++;
            }
            event(new PremiseComplianceRulePackApplied($pack, $premise, $created));
            return $created;
        });
    }

    private function validateTemplate(array $template): void
    {
        $type = $template['requirement_type'] ?? 'custom';
        if (!in_array($type, PremiseComplianceRequirement::TYPES, true)) {
            throw ValidationException::withMessages(['rule_pack' => "Unsupported requirement type in rule pack: {$type}."]);
        }
        $recurrence = $template['recurrence_type'] ?? 'none';
        if (!in_array($recurrence, \App\Domains\WorkCore\System\Modules\Premises\Domain\Compliance\ComplianceRecurrence::TYPES, true)) {
            throw ValidationException::withMessages(['rule_pack' => "Unsupported recurrence type in rule pack: {$recurrence}."]);
        }
        if (in_array($recurrence, ['days', 'months', 'years'], true) && (int) ($template['recurrence_interval'] ?? 0) < 1) {
            throw ValidationException::withMessages(['rule_pack' => 'Recurring rule-pack requirements need a positive interval.']);
        }
        if ($recurrence === 'annual') {
            $month = (int) ($template['annual_month'] ?? 0);
            $day = (int) ($template['annual_day'] ?? 0);
            if ($month < 1 || $month > 12 || $day < 1 || $day > 31) {
                throw ValidationException::withMessages(['rule_pack' => 'Annual rule-pack requirements need a valid month and day.']);
            }
        }
    }

    private function actorId(): ?int
    {
        return function_exists('user') && user() ? user()->id : auth()->id();
    }
}
