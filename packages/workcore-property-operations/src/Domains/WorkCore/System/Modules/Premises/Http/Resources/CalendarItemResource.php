<?php
namespace App\Domains\WorkCore\System\Modules\Premises\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class CalendarItemResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'type' => $this['type'] ?? null,
            'id' => $this['id'] ?? null,
            'property_id' => $this['property_id'] ?? null,
            'title' => $this['title'] ?? null,
            'start' => $this['start'] ?? null,
            'status' => $this['status'] ?? null,
        ];
    }
}
