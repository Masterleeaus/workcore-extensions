<?php

namespace App\Domains\WorkCore\System\Modules\Premises\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class Settings
{
    private const TABLE = 'pm_settings';
    private const CACHE_PREFIX = 'pm_settings_';

    public static function get(int $companyId): array
    {
        return Cache::remember(self::CACHE_PREFIX.$companyId, 3600, function () use ($companyId) {
            $row = DB::table(self::TABLE)->where('company_id', $companyId)->first();
            return $row ? (array) json_decode($row->settings_json ?? '{}', true) : [];
        });
    }

    public static function set(int $companyId, array $settings): void
    {
        DB::table(self::TABLE)->updateOrInsert(
            ['company_id' => $companyId],
            ['settings_json' => json_encode($settings, JSON_UNESCAPED_UNICODE), 'updated_at' => now(), 'created_at' => now()]
        );

        Cache::forget(self::CACHE_PREFIX.$companyId);
    }
}
