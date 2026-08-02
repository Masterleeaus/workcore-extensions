<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Contracts;

use App\Domains\WorkCore\System\Modules\Finance\Application\DTOs\ActionContext;
use App\Domains\WorkCore\System\Modules\Finance\Domain\Payments\PaymentInstructions;
use App\Domains\WorkCore\System\Modules\Finance\Domain\Payments\QrArtifact;

interface PaymentInstructionDeliveryGateway
{
    /** @param list<string> $channels @return list<array<string,mixed>> */
    public function deliver(
        PaymentInstructions $instructions,
        array $channels,
        ActionContext $context,
        ?QrArtifact $qrArtifact = null,
    ): array;
}
