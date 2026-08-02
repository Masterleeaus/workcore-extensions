<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Domain\Commercial;

enum QuoteState: string { case Draft='draft'; case Ready='ready'; case Sent='sent'; case Viewed='viewed'; case Revised='revised'; case Accepted='accepted'; case Declined='declined'; case Expired='expired'; case Converted='converted'; }
