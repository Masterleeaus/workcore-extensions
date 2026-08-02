<?php
namespace App\Domains\WorkCore\System\Modules\Premises\Events;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use App\Domains\WorkCore\System\Modules\Premises\Entities\PremiseIncident;
class PremiseIncidentStatusChanged { use Dispatchable, SerializesModels; public function __construct(public PremiseIncident $incident, public string $previousStatus) {} }
