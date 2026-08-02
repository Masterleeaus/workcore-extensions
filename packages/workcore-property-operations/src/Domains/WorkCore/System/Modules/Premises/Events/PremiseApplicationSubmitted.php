<?php

namespace App\Domains\WorkCore\System\Modules\Premises\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use App\Domains\WorkCore\System\Modules\Premises\Entities\PremiseApplication;

class PremiseApplicationSubmitted
{
    use Dispatchable, SerializesModels;

    public function __construct(public PremiseApplication $application) {}
}
