<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Support\AI;

use App\Domains\WorkCore\System\AI\Tools\{AiToolDefinition,DomainToolRegistryContract,ToolDefinitionFactory as T};

final class SupportToolRegistry implements DomainToolRegistryContract
{
    /** @return list<AiToolDefinition> */
    public function registerTools(): array
    {
        $id = T::string(26);
        return [
            T::read('support_search_tickets', 'Search company support tickets using structured filters.', 'workcore.support_ticket.search', T::object([
                'filters' => T::freeObject(), 'perPage' => T::integer(1,100),
            ])),
            T::action('support_create_ticket', 'Create a support ticket through WorkCore.', 'workcore.support_ticket.create', T::object([
                'queue_public_id' => $id, 'customer_public_id' => $id, 'contact_public_id' => $id,
                'subject' => T::string(255), 'description' => T::string(20000),
                'priority' => T::string(20, ['low','normal','high','urgent']), 'source' => T::string(40),
                'metadata' => T::freeObject(),
            ], ['subject','description'], true)),
            T::action('support_add_message', 'Add a public or internal message to a support ticket.', 'workcore.support_ticket.message.add', T::object([
                'support_ticket_public_id' => $id, 'body' => T::string(20000),
                'visibility' => T::string(20, ['public','internal']), 'direction' => T::string(20, ['inbound','outbound']),
                'metadata' => T::freeObject(),
            ], ['support_ticket_public_id','body'])),
            T::action('support_assign_ticket', 'Assign a support ticket to a user or queue.', 'workcore.support_ticket.assign', T::object([
                'support_ticket_public_id' => $id, 'assigned_user_id' => T::integer(1),
                'queue_public_id' => $id, 'reason' => T::string(5000),
            ], ['support_ticket_public_id'], true)),
            T::action('support_change_status', 'Change a support ticket status with an optional reason.', 'workcore.support_ticket.change_status', T::object([
                'support_ticket_public_id' => $id,
                'status' => T::string(30, ['open','pending','on_hold','resolved','closed']),
                'reason' => T::string(5000),
            ], ['support_ticket_public_id','status'])),
        ];
    }
}
