<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Infrastructure\Payments;

use App\Domains\WorkCore\System\Modules\Finance\Contracts\QrCodeRenderer;
use App\Domains\WorkCore\System\Modules\Finance\Domain\Payments\PaymentQrPayload;
use BaconQrCode\Renderer\Image\ImageRenderer;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use RuntimeException;

final class BaconQrCodeRenderer implements QrCodeRenderer
{
    public function __construct(private readonly int $size = 320)
    {
    }

    public function render(PaymentQrPayload $payload): string
    {
        if (! class_exists(Writer::class)) {
            throw new RuntimeException('bacon/bacon-qr-code is required to render payment QR codes.');
        }
        $renderer = new ImageRenderer(new RendererStyle($this->size), new SvgImageBackEnd());
        return (new Writer($renderer))->writeString($payload->content);
    }
}
