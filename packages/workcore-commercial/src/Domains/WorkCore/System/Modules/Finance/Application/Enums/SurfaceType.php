<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Application\Enums;

enum SurfaceType: string
{
    case Workspace = 'workspace';
    case MobileCard = 'mobile_card';
    case TabletPanel = 'tablet_panel';
    case CustomerPortal = 'customer_portal';
    case VoicePrompt = 'voice_prompt';
    case Headless = 'headless';
}
