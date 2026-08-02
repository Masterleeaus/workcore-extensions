<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Workforce\AI;

use App\Domains\WorkCore\System\AI\Tools\{AiToolDefinition,DomainToolRegistryContract,ToolDefinitionFactory as T};

final class WorkforceToolRegistry implements DomainToolRegistryContract
{
    /** @return list<AiToolDefinition> */
    public function registerTools(): array
    {
        $id=T::string(26);
        return [
            T::read('workforce_search_workers', 'Search company workers by name, status or skill.', 'workcore.worker.search', T::object([
                'query'=>T::string(255),'status'=>T::string(40),'skillPublicId'=>$id,'perPage'=>T::integer(1,100),
            ])),
            T::action('workforce_create_worker', 'Create a worker profile through WorkCore.', 'workcore.worker.create', T::object([
                'display_name'=>T::string(255),'first_name'=>T::string(120),'last_name'=>T::string(120),
                'email'=>T::string(320),'phone'=>T::string(80),'status'=>T::string(40),
                'employment_type'=>T::string(80),'user_id'=>T::integer(1),'metadata'=>T::freeObject(),
            ], ['display_name'], true)),
            T::action('workforce_assign_skill', 'Assign a skill and proficiency level to a worker.', 'workcore.worker.assign_skill', T::object([
                'worker_public_id'=>$id,'skill_public_id'=>$id,'proficiency'=>T::string(40),
                'verified'=>T::boolean(),'notes'=>T::string(5000),
            ], ['worker_public_id','skill_public_id'])),
        ];
    }
}
