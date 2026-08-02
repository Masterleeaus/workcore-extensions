<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Automation;

enum AutomationDecision: string { case Propose='propose'; case RequestApproval='request_approval'; case Execute='execute'; case Block='block'; }
