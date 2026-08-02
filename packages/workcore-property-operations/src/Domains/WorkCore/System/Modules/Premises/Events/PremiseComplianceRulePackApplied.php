<?php
namespace App\Domains\WorkCore\System\Modules\Premises\Events;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use App\Domains\WorkCore\System\Modules\Premises\Entities\PremiseComplianceRulePack;
use App\Domains\WorkCore\System\Modules\Premises\Entities\Property;
class PremiseComplianceRulePackApplied { use Dispatchable, SerializesModels; public function __construct(public PremiseComplianceRulePack $rulePack, public Property $premise, public int $createdCount) {} }
