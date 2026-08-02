<?php
namespace App\Domains\WorkCore\System\Modules\Premises\Events;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use App\Domains\WorkCore\System\Modules\Premises\Entities\PremiseConditionRecord;
class PremiseConditionStatusChanged { use Dispatchable, SerializesModels; public function __construct(public PremiseConditionRecord $conditionRecord, public string $previousStatus) {} }
