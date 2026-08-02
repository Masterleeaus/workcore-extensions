<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Infrastructure\Runtime;

use App\Domains\WorkCore\System\Modules\Finance\Application\ActionRegistry;
use App\Domains\WorkCore\System\Modules\Finance\Application\DTOs\ActionContext;
use App\Domains\WorkCore\System\Modules\Finance\Application\DTOs\ActionResult;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\ActionInputFactory;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\FinancialActionInvoker;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\TitanMoneyAction;
use Illuminate\Contracts\Container\Container;
use RuntimeException;

final class LaravelFinancialActionInvoker implements FinancialActionInvoker
{
    public function __construct(private readonly ActionRegistry $registry,private readonly ActionInputFactory $inputs,private readonly Container $container){}
    public function invoke(string $actionKey,array $payload,ActionContext $context):ActionResult{$definition=$this->registry->get($actionKey);$action=$this->container->make($definition->actionClass);if(!$action instanceof TitanMoneyAction)throw new RuntimeException('Registered finance action must implement TitanMoneyAction.');return $action->execute($this->inputs->make($actionKey,$payload),$context);}
}
