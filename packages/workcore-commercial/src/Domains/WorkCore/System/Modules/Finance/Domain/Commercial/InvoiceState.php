<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Domain\Commercial;

enum InvoiceState: string { case Draft='draft'; case Approved='approved'; case Issued='issued'; case Delivered='delivered'; case Viewed='viewed'; case PartiallyPaid='partially_paid'; case Paid='paid'; case Overdue='overdue'; case Voided='voided'; case Credited='credited'; }
