<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Verticals;

use App\Domains\WorkCore\System\Models\BusinessLine;
use App\Domains\WorkCore\System\Models\CompanyVertical;
use App\Domains\WorkCore\System\Models\VerticalInstallation;
use DomainException;
use Illuminate\Database\ConnectionInterface;
use InvalidArgumentException;

final class CompanyVerticalManager
{
    public function __construct(
        private ConnectionInterface $db,
        private VerticalRegistry $registry,
        private CompanyVerticalProfileResolver $profiles,
    ) {}

    public function activate(
        int $companyId,
        string $verticalKey,
        bool $primary = true,
        ?int $actorId = null,
        ?string $businessLineName = null,
        bool $createDefaultBusinessLine = true,
    ): CompanyVertical {
        $definition = $this->registry->get($verticalKey);
        if ($definition->type === 'foundation') {
            throw new InvalidArgumentException("Foundation pack [{$verticalKey}] cannot be selected directly.");
        }

        if (! $primary
            && (bool) config('workcore.verticals.require_primary_for_addons', true)
            && ! CompanyVertical::query()
                ->where('company_id', $companyId)
                ->where('status', 'active')
                ->where('is_primary', true)
                ->exists()) {
            throw new DomainException('A company must select a primary vertical before adding another business line.');
        }

        /** @var CompanyVertical $selection */
        $selection = $this->db->transaction(function () use (
            $companyId,
            $verticalKey,
            $primary,
            $actorId,
            $businessLineName,
            $definition,
            $createDefaultBusinessLine,
        ): CompanyVertical {
            if ($primary) {
                CompanyVertical::query()
                    ->where('company_id', $companyId)
                    ->where('is_primary', true)
                    ->update(['is_primary' => false]);

                BusinessLine::query()
                    ->where('company_id', $companyId)
                    ->update(['is_default' => false]);
            }

            $selection = CompanyVertical::query()->updateOrCreate(
                ['company_id' => $companyId, 'vertical_key' => $verticalKey],
                [
                    'pack_version' => $definition->version,
                    'is_primary' => $primary,
                    'status' => 'active',
                    'activated_by_user_id' => $actorId,
                    'activated_at' => now(),
                    'deactivated_at' => null,
                ],
            );

            if ($createDefaultBusinessLine) {
                $line = BusinessLine::query()
                    ->withTrashed()
                    ->firstOrNew(['company_id' => $companyId, 'key' => $verticalKey]);
                $line->fill([
                    'company_vertical_id' => $selection->id,
                    'name' => $businessLineName !== null && trim($businessLineName) !== ''
                        ? trim($businessLineName)
                        : $definition->label,
                    'is_default' => $primary,
                    'status' => 'active',
                ]);
                $line->deleted_at = null;
                $line->save();
            }

            VerticalInstallation::query()->updateOrCreate(
                ['company_id' => $companyId, 'vertical_key' => $verticalKey],
                [
                    'installed_version' => $definition->version,
                    'status' => 'installed',
                    'manifest_hash' => $definition->source !== null && is_file($definition->source)
                        ? hash_file('sha256', $definition->source)
                        : null,
                    'installed_by_user_id' => $actorId,
                    'installed_at' => now(),
                    'deactivated_at' => null,
                    'metadata' => ['type' => $definition->type, 'label' => $definition->label],
                ],
            );

            return $selection->refresh();
        }, 3);

        $this->profiles->forget($companyId);

        return $selection;
    }


    /** @param array<string,mixed> $settings */
    public function syncBusinessLine(
        int $companyId,
        string $verticalKey,
        string $key,
        string $name,
        bool $isDefault = false,
        array $settings = [],
    ): BusinessLine {
        $selection = CompanyVertical::query()
            ->where('company_id', $companyId)
            ->where('vertical_key', $verticalKey)
            ->where('status', 'active')
            ->firstOrFail();

        if ($isDefault) {
            BusinessLine::query()->where('company_id', $companyId)->update(['is_default' => false]);
        }

        $line = BusinessLine::query()
            ->withTrashed()
            ->firstOrNew(['company_id' => $companyId, 'key' => $key]);
        $line->fill([
            'company_vertical_id' => $selection->id,
            'name' => trim($name),
            'is_default' => $isDefault,
            'status' => 'active',
            'settings' => $settings,
        ]);
        $line->deleted_at = null;
        $line->save();
        $this->profiles->forget($companyId);

        return $line->refresh();
    }

    public function deactivate(int $companyId, string $verticalKey): void
    {
        $selection = CompanyVertical::query()
            ->where('company_id', $companyId)
            ->where('vertical_key', $verticalKey)
            ->firstOrFail();

        if ($selection->is_primary
            && CompanyVertical::query()
                ->where('company_id', $companyId)
                ->where('status', 'active')
                ->where('is_primary', false)
                ->exists()) {
            throw new DomainException('Deactivate add-on business lines or select a replacement primary vertical first.');
        }

        $this->db->transaction(function () use ($companyId, $verticalKey, $selection): void {
            $selection->forceFill([
                'is_primary' => false,
                'status' => 'inactive',
                'deactivated_at' => now(),
            ])->save();

            BusinessLine::query()
                ->where('company_id', $companyId)
                ->where('company_vertical_id', $selection->id)
                ->update(['is_default' => false, 'status' => 'inactive']);

            VerticalInstallation::query()
                ->where('company_id', $companyId)
                ->where('vertical_key', $verticalKey)
                ->update(['status' => 'inactive', 'deactivated_at' => now()]);
        }, 3);

        $this->profiles->forget($companyId);
    }
}
