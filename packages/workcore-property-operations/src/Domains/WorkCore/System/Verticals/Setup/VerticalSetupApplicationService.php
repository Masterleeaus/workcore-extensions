<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Verticals\Setup;

use App\Domains\WorkCore\System\Models\BusinessLine;
use App\Domains\WorkCore\System\Models\CompanyDocumentTemplate;
use App\Domains\WorkCore\System\Models\CompanyFeature;
use App\Domains\WorkCore\System\Models\CompanyLocale;
use App\Domains\WorkCore\System\Models\CompanySetting;
use App\Domains\WorkCore\System\Models\CompanyTerminologyOverride;
use App\Domains\WorkCore\System\Models\CompanyVertical;
use App\Domains\WorkCore\System\Models\VerticalSeedProvenance;
use App\Domains\WorkCore\System\Models\VerticalSetupSession;
use App\Domains\WorkCore\System\Verticals\CompanyVerticalManager;
use App\Domains\WorkCore\System\Verticals\Operations\OperationalSeedInstaller;
use App\Domains\WorkCore\System\Verticals\CompanyVerticalProfileResolver;
use App\Domains\WorkCore\System\Verticals\VerticalRegistry;
use Illuminate\Database\ConnectionInterface;

final class VerticalSetupApplicationService
{
    public function __construct(
        private ConnectionInterface $db,
        private CompanyVerticalManager $manager,
        private CompanyVerticalProfileResolver $profiles,
        private VerticalRegistry $registry,
        private OperationalSeedInstaller $operationalSeeds,
    ) {}

    /** @return array<string,mixed> */
    public function apply(int $companyId, int $actorId, VerticalSetupPlan $plan): array
    {
        $payload = $plan->toArray();
        $planHash = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));

        $result = $this->db->transaction(function () use ($companyId, $actorId, $plan, $payload, $planHash): array {
            $previousSession = VerticalSetupSession::query()
                ->where('company_id', $companyId)
                ->where('status', 'applied')
                ->orderByDesc('applied_at')
                ->first();
            $previousPlan = (array) ($previousSession?->compiled_plan ?? []);

            $this->manager->activate($companyId, $plan->primaryVertical, true, $actorId, null, false);
            foreach ($plan->addOns as $verticalKey) {
                $this->manager->activate($companyId, $verticalKey, false, $actorId, null, false);
            }

            $selectedVerticals = array_fill_keys(array_merge([$plan->primaryVertical], $plan->addOns), true);
            $activeVerticals = CompanyVertical::query()
                ->where('company_id', $companyId)
                ->where('status', 'active')
                ->pluck('vertical_key')
                ->map(static fn (mixed $key): string => (string) $key)
                ->all();
            foreach ($activeVerticals as $verticalKey) {
                if (! isset($selectedVerticals[$verticalKey])) {
                    $this->manager->deactivate($companyId, $verticalKey);
                }
            }

            $businessLines = [];
            $plannedBusinessLineIds = [];
            foreach ($plan->businessLines as $line) {
                $record = $this->manager->syncBusinessLine(
                    companyId: $companyId,
                    verticalKey: (string) $line['vertical_key'],
                    key: (string) $line['key'],
                    name: (string) $line['name'],
                    isDefault: (bool) ($line['is_default'] ?? false),
                    settings: (array) ($line['settings'] ?? []),
                );
                $plannedBusinessLineIds[] = (int) $record->id;
                $businessLines[] = ['public_id' => $record->public_id, 'key' => $record->key, 'vertical_key' => (string) $line['vertical_key']];
                $this->recordSeed($companyId, (string) $line['vertical_key'], 'business_line.' . $line['key'], 'business_line', (int) $record->id, (array) $line);
            }

            $operations = $this->operationalSeeds->synchronise($companyId, $actorId, $plan->operations, $businessLines);

            $managedBusinessLineIds = VerticalSeedProvenance::query()
                ->where('company_id', $companyId)
                ->where('record_type', 'business_line')
                ->whereNotNull('record_id')
                ->pluck('record_id')
                ->map(static fn (mixed $id): int => (int) $id)
                ->all();
            $obsoleteBusinessLineIds = array_values(array_diff($managedBusinessLineIds, $plannedBusinessLineIds));
            if ($obsoleteBusinessLineIds !== []) {
                BusinessLine::query()
                    ->where('company_id', $companyId)
                    ->whereIn('id', $obsoleteBusinessLineIds)
                    ->update(['is_default' => false, 'status' => 'inactive']);
            }

            $featureKeys = array_keys($plan->features);
            $obsoleteFeatures = CompanyFeature::query()
                ->where('company_id', $companyId)
                ->where('scope_type', 'company')
                ->where('scope_id', $companyId)
                ->where('source', 'vertical_setup');
            if ($featureKeys !== []) {
                $obsoleteFeatures->whereNotIn('feature_key', $featureKeys);
            }
            $obsoleteFeatures->delete();
            foreach ($plan->features as $featureKey => $enabled) {
                CompanyFeature::query()->updateOrCreate(
                    ['company_id' => $companyId, 'scope_type' => 'company', 'scope_id' => $companyId, 'feature_key' => $featureKey],
                    ['enabled' => $enabled, 'source' => 'vertical_setup', 'settings' => null],
                );
            }

            foreach ((array) ($previousPlan['terminology'] ?? []) as $termKey => $previousValue) {
                if (array_key_exists((string) $termKey, $plan->terminology)) {
                    continue;
                }
                CompanyTerminologyOverride::query()
                    ->where('company_id', $companyId)
                    ->where('scope_type', 'company')
                    ->where('scope_id', $companyId)
                    ->where('locale', '*')
                    ->where('term_key', (string) $termKey)
                    ->where('term_value', (string) $previousValue)
                    ->delete();
            }
            foreach ($plan->terminology as $termKey => $termValue) {
                CompanyTerminologyOverride::query()->updateOrCreate(
                    ['company_id' => $companyId, 'scope_type' => 'company', 'scope_id' => $companyId, 'locale' => '*', 'term_key' => $termKey],
                    ['term_value' => $termValue],
                );
            }

            $localeCodes = array_values(array_map(static fn (array $locale): string => (string) $locale['locale'], $plan->locales));
            $obsoleteLocales = CompanyLocale::query()
                ->where('company_id', $companyId)
                ->where('source', 'vertical_setup');
            if ($localeCodes !== []) {
                $obsoleteLocales->whereNotIn('locale', $localeCodes);
            }
            $obsoleteLocales->update(['is_default' => false, 'is_active' => false]);
            if ($plan->locales !== []) {
                CompanyLocale::query()->where('company_id', $companyId)->update(['is_default' => false]);
            }
            foreach ($plan->locales as $locale) {
                CompanyLocale::query()->updateOrCreate(
                    ['company_id' => $companyId, 'locale' => (string) $locale['locale']],
                    [
                        'display_name' => (string) $locale['display_name'],
                        'direction' => (string) $locale['direction'],
                        'is_default' => (bool) $locale['is_default'],
                        'is_active' => (bool) $locale['is_active'],
                        'source' => (string) ($locale['source'] ?? 'vertical_setup'),
                        'settings' => (array) ($locale['settings'] ?? []),
                    ],
                );
            }

            $settingKeys = array_keys($plan->settings);
            $obsoleteSettings = CompanySetting::query()
                ->where('company_id', $companyId)
                ->where('source', 'vertical_setup')
                ->where('is_locked', false);
            if ($settingKeys !== []) {
                $obsoleteSettings->whereNotIn('setting_key', $settingKeys);
            }
            $obsoleteSettings->delete();
            foreach ($plan->settings as $settingKey => $value) {
                $locked = CompanySetting::query()
                    ->where('company_id', $companyId)
                    ->where('setting_key', $settingKey)
                    ->where('is_locked', true)
                    ->exists();
                if ($locked) {
                    continue;
                }
                CompanySetting::query()->updateOrCreate(
                    ['company_id' => $companyId, 'setting_key' => $settingKey],
                    ['setting_group' => $this->settingGroup($settingKey), 'value' => ['value' => $value], 'source' => 'vertical_setup', 'is_locked' => false],
                );
            }

            $documents = [];
            $plannedDocumentIds = [];
            foreach ($plan->documents as $document) {
                $existingDocument = CompanyDocumentTemplate::query()
                    ->where('company_id', $companyId)
                    ->where('document_key', (string) $document['document_key'])
                    ->where('locale', (string) $document['locale'])
                    ->first();
                $customerModified = $existingDocument !== null && VerticalSeedProvenance::query()
                    ->where('company_id', $companyId)
                    ->where('record_type', 'company_document_template')
                    ->where('record_id', $existingDocument->id)
                    ->where('is_customer_modified', true)
                    ->exists();
                $record = $customerModified ? $existingDocument : CompanyDocumentTemplate::query()->updateOrCreate(
                    ['company_id' => $companyId, 'document_key' => (string) $document['document_key'], 'locale' => (string) $document['locale']],
                    [
                        'business_line_id' => null,
                        'vertical_key' => $plan->primaryVertical,
                        'document_type' => (string) $document['document_type'],
                        'title' => (string) $document['title'],
                        'content' => (string) $document['content'],
                        'metadata' => (array) ($document['metadata'] ?? []),
                        'source_version' => $this->registry->get($plan->primaryVertical)->version,
                        'is_active' => true,
                    ],
                );
                $plannedDocumentIds[] = (int) $record->id;
                $documents[] = ['public_id' => $record->public_id, 'document_key' => $record->document_key, 'locale' => $record->locale];
                $this->recordSeed($companyId, $plan->primaryVertical, 'document.' . $record->document_key . '.' . $record->locale, 'company_document_template', (int) $record->id, (array) $document);
            }
            $managedDocumentIds = VerticalSeedProvenance::query()
                ->where('company_id', $companyId)
                ->where('record_type', 'company_document_template')
                ->where('is_customer_modified', false)
                ->pluck('record_id')
                ->map(static fn (mixed $id): int => (int) $id)
                ->all();
            $obsoleteDocumentIds = array_values(array_diff($managedDocumentIds, $plannedDocumentIds));
            if ($obsoleteDocumentIds !== []) {
                CompanyDocumentTemplate::query()
                    ->where('company_id', $companyId)
                    ->whereIn('id', $obsoleteDocumentIds)
                    ->update(['is_active' => false]);
            }

            $session = VerticalSetupSession::query()->updateOrCreate(
                ['company_id' => $companyId, 'plan_hash' => $planHash],
                [
                    'actor_user_id' => $actorId,
                    'primary_vertical_key' => $plan->primaryVertical,
                    'add_on_vertical_keys' => $plan->addOns,
                    'status' => 'applied',
                    'answers' => $plan->settings,
                    'compiled_plan' => $payload,
                    'last_error' => null,
                    'started_at' => now(),
                    'last_interaction_at' => now(),
                    'applied_at' => now(),
                ],
            );

            return [
                'setup_session_public_id' => $session->public_id,
                'plan_hash' => $planHash,
                'primary_vertical' => $plan->primaryVertical,
                'add_ons' => $plan->addOns,
                'business_lines' => $businessLines,
                'operations' => $operations,
                'documents' => $documents,
            ];
        }, 3);

        $this->profiles->forget($companyId);
        $profile = $this->profiles->resolve($companyId);
        $result['profile'] = [
            'primary_vertical' => $profile->primaryVertical,
            'packs' => $profile->packKeys,
            'features' => $profile->features,
            'terminology' => $profile->terminology,
            'codebooks' => $profile->codebooks,
            'workflows' => $profile->workflows,
        ];

        return $result;
    }

    /** @param array<string,mixed> $content */
    private function recordSeed(int $companyId, string $verticalKey, string $seedKey, string $recordType, int $recordId, array $content): void
    {
        $identity = ['company_id' => $companyId, 'vertical_key' => $verticalKey, 'seed_key' => $seedKey, 'record_type' => $recordType];
        $existing = VerticalSeedProvenance::query()->where($identity)->first();
        VerticalSeedProvenance::query()->updateOrCreate(
            $identity,
            [
                'record_id' => $recordId,
                'installed_version' => $this->registry->get($verticalKey)->version,
                'content_hash' => (bool) ($existing?->is_customer_modified ?? false)
                    ? $existing?->content_hash
                    : hash('sha256', json_encode($content, JSON_THROW_ON_ERROR)),
                'is_customer_modified' => (bool) ($existing?->is_customer_modified ?? false),
                'metadata' => ['source' => 'vertical_setup'],
            ],
        );
    }

    private function settingGroup(string $key): string
    {
        $group = explode('.', $key, 2)[0] ?: 'vertical_setup';
        return substr($group, 0, 80);
    }
}
