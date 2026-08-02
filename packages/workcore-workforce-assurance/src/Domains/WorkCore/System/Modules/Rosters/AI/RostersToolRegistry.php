<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Rosters\AI;

use App\Domains\WorkCore\System\AI\Tools\{AiToolDefinition,DomainToolRegistryContract,ToolDefinitionFactory as T};

final class RostersToolRegistry implements DomainToolRegistryContract
{
    /** @return list<AiToolDefinition> */
    public function registerTools(): array
    {
        $id=T::string(26); $date=T::string(40);
        return [
            T::read('rosters_search', 'Search rosters by status, type, premises and date window.', 'workcore.roster.search', T::object([
                'query'=>T::string(255),'status'=>T::string(40),'rosterType'=>T::string(80),'premisesPublicId'=>$id,
                'startsFrom'=>$date,'startsTo'=>$date,'perPage'=>T::integer(1,100),
            ])),
            T::read('rosters_search_availability', 'Search worker availability rules.', 'workcore.availability.search', T::object([
                'workerPublicId'=>$id,'availabilityType'=>T::string(50),'weekday'=>T::integer(0,6),'perPage'=>T::integer(1,100),
            ])),
            T::action('rosters_create', 'Create a draft company roster.', 'workcore.roster.create', T::object([
                'name'=>T::string(255),'roster_type'=>T::string(80),'premises_public_id'=>$id,
                'starts_at'=>$date,'ends_at'=>$date,'timezone'=>T::string(80),'notes'=>T::string(10000),'metadata'=>T::freeObject(),
            ], ['name','starts_at','ends_at'])),
            T::action('rosters_assign_shift', 'Assign a roster shift to a worker.', 'workcore.roster_shift.assign', T::object([
                'shift_public_id'=>$id,'worker_public_id'=>$id,'reason'=>T::string(5000),
            ], ['shift_public_id','worker_public_id'])),
            T::action('rosters_publish', 'Publish a completed roster.', 'workcore.roster.publish', T::object([
                'roster_public_id'=>$id,
            ], ['roster_public_id'])),
        ];
    }
}
