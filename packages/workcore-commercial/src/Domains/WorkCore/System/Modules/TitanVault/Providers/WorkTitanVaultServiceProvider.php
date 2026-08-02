<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\TitanVault\Providers;

use App\Domains\WorkCore\System\Modules\TitanVault\ReadModels\SearchVaultDocuments;
use App\Domains\WorkCore\System\ReadModels\ReadModelDefinition;
use App\Domains\WorkCore\System\ReadModels\ReadModelRegistry;
use Illuminate\Support\ServiceProvider;

final class WorkTitanVaultServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->make(ReadModelRegistry::class)->register(new ReadModelDefinition(
            'workcore.vault.document.search',
            SearchVaultDocuments::class,
            'workcore.vault',
            ['metadata_only' => true],
            (string) config('workcore.vault.permissions.view'),
        ));
    }
}
