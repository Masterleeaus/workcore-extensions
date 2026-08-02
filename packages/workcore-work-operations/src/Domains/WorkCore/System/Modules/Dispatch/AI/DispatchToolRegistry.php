<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Dispatch\AI;

use App\Domains\WorkCore\System\AI\Tools\{AiToolDefinition,DomainToolRegistryContract,ToolDefinitionFactory as T};

final class DispatchToolRegistry implements DomainToolRegistryContract
{
    /** @return list<AiToolDefinition> */
    public function registerTools(): array
    {
        $id = T::string(26);
        $date = T::string(40);
        return [
            T::read('dispatch_search_board', 'Search the company dispatch board by status, assignee, premises and schedule window.', 'workcore.dispatch.search', T::object([
                'query' => T::string(255), 'status' => T::string(40), 'assignmentType' => T::string(80),
                'assignedUserId' => T::integer(1), 'premisesPublicId' => $id,
                'scheduledFrom' => $date, 'scheduledTo' => $date, 'perPage' => T::integer(1,100),
            ])),
            T::action('dispatch_assign_work', 'Assign a work order or appointment to a worker through WorkCore dispatch.', 'workcore.dispatch.assign', T::object([
                'dispatch_number' => T::string(100), 'work_order_public_id' => $id, 'appointment_public_id' => $id,
                'assigned_user_id' => T::integer(1), 'assignment_type' => T::string(80),
                'priority' => T::string(20, ['low','normal','high','urgent','emergency']),
                'scheduled_start' => $date, 'scheduled_end' => $date, 'timezone' => T::string(80),
                'route_group' => T::string(100), 'sequence' => T::integer(0), 'instructions' => T::string(10000),
                'access_notes' => T::string(10000), 'internal_notes' => T::string(10000),
                'source' => T::string(50), 'allow_conflicts' => T::boolean(),
            ], ['assigned_user_id'], true)),
            T::action('dispatch_reassign_work', 'Reassign a dispatch assignment with an auditable reason.', 'workcore.dispatch.reassign', T::object([
                'dispatch_public_id' => $id, 'assigned_user_id' => T::integer(1), 'scheduled_start' => $date,
                'scheduled_end' => $date, 'timezone' => T::string(80), 'reason' => T::string(5000),
                'allow_conflicts' => T::boolean(),
            ], ['dispatch_public_id','assigned_user_id','reason'])),
            T::action('dispatch_change_status', 'Change dispatch execution status with optional reason and location evidence.', 'workcore.dispatch.change_status', T::object([
                'dispatch_public_id' => $id,
                'status' => T::string(30, ['assigned','dispatched','en_route','arrived','in_progress','completed','cancelled','failed']),
                'reason' => T::string(5000), 'latitude' => T::number(-90,90), 'longitude' => T::number(-180,180),
            ], ['dispatch_public_id','status'])),
        ];
    }
}
