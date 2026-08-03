<?php

declare(strict_types=1);

namespace App\Extensions\WorkCore\System\Console\Commands;

use App\Extensions\WorkCore\System\Navigation\MagicAIMenuSynchronizer;
use Illuminate\Console\Command;

final class SyncWorkCoreMenusCommand extends Command
{
    protected $signature = 'workcore:sync-menus';

    protected $description = 'Synchronize extension-owned WorkCore navigation into the MagicAI menu table.';

    public function __construct(private MagicAIMenuSynchronizer $synchronizer)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $result = $this->synchronizer->sync();
        $this->components->info(sprintf(
            'WorkCore menus synchronized: %d created, %d updated, %d unchanged, %d disabled.',
            $result['created'],
            $result['updated'],
            $result['unchanged'],
            $result['disabled'],
        ));

        return self::SUCCESS;
    }
}
