<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Automation;

enum AutonomyLevel: string { case Assist='assist'; case Approve='approve'; case Auto='auto'; case Managed='managed_autonomous'; }
