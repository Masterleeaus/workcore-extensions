<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Domain\EInvoicing;

enum EInvoiceFormat: string
{
    case PeppolBisBilling3 = 'peppol_bis_billing_3';
    case Ubl21 = 'ubl_2_1';
    case CustomXml = 'custom_xml';
}
