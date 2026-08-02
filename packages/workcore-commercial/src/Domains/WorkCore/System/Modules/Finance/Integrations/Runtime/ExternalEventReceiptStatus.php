<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Integrations\Runtime;

enum ExternalEventReceiptStatus: string { case Acquired='acquired'; case Replay='replay'; case Conflict='conflict'; }
