<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Audit;

enum ActorType: string
{
    case User = 'user';
    case AiAgent = 'ai_agent';
    case Workflow = 'workflow';
    case Integration = 'integration';
    case Device = 'device';
    case System = 'system';
}
