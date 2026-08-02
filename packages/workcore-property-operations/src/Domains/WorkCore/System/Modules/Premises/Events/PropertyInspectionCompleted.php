<?php
namespace App\Domains\WorkCore\System\Modules\Premises\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use App\Domains\WorkCore\System\Modules\Premises\Entities\PropertyInspection;

/**
 * @deprecated Inspection ownership has moved to QualityControl.
 *             New code should listen to QualityControl events instead.
 *             This event is kept for backward compatibility with existing listeners
 *             that fire when legacy pm_property_inspections rows are touched.
 */
class PropertyInspectionCompleted
{
    use Dispatchable, SerializesModels;

    public function __construct(public PropertyInspection $inspection) {}
}
