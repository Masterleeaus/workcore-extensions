<?php

declare(strict_types=1);

/**
 * Merge these entries into
 * App\Domains\Marketplace\MarketplaceServiceProvider::$extensionProviders.
 * Parent order is mandatory because add-ons depend on the WorkCore runtime
 * autoloader and WorkModuleRegistry being registered first.
 */
return [
    'workcore' => App\Extensions\WorkCore\System\WorkCoreServiceProvider::class,
    'workcore-business-network' => App\Extensions\WorkCoreBusinessNetwork\System\WorkCoreBusinessNetworkServiceProvider::class,
    'workcore-commercial' => App\Extensions\WorkCoreCommercial\System\WorkCoreCommercialServiceProvider::class,
    'workcore-work-operations' => App\Extensions\WorkCoreWorkOperations\System\WorkCoreWorkOperationsServiceProvider::class,
    'workcore-property-operations' => App\Extensions\WorkCorePropertyOperations\System\WorkCorePropertyOperationsServiceProvider::class,
    'workcore-workforce-assurance' => App\Extensions\WorkCoreWorkforceAssurance\System\WorkCoreWorkforceAssuranceServiceProvider::class,
];
