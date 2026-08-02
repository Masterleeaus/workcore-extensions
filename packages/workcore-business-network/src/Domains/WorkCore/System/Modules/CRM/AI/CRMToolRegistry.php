<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\CRM\AI;

use App\Domains\WorkCore\System\AI\Tools\{AiToolDefinition,DomainToolRegistryContract,ToolDefinitionFactory as T};

final class CRMToolRegistry implements DomainToolRegistryContract
{
    /** @return list<AiToolDefinition> */
    public function registerTools(): array
    {
        $id = T::string(26);
        $text = T::string(10000);

        return [
            T::read('crm_search_customers', 'Search company-scoped customers by name, email, company or phone.', 'workcore.customer.search', T::object([
                'query' => T::string(255), 'perPage' => T::integer(1, 100),
            ])),
            T::read('crm_search_leads', 'Search company-scoped sales leads.', 'workcore.lead.search', T::object([
                'query' => T::string(255), 'perPage' => T::integer(1, 100),
            ])),
            T::read('crm_list_sales_pipelines', 'List active company sales pipelines and stages.', 'workcore.sales_pipeline.list', T::object()),
            T::read('crm_view_pipeline_board', 'View opportunities grouped by stage for one sales pipeline.', 'workcore.sales_pipeline.board', T::object([
                'pipelinePublicId' => $id,
            ], ['pipelinePublicId'])),
            T::read('crm_search_opportunities', 'Search sales opportunities with company-scoped filters.', 'workcore.opportunity.search', T::object([
                'filters' => T::freeObject(), 'perPage' => T::integer(1, 100),
            ])),
            T::read('crm_get_forecast', 'Calculate open, weighted, won and lost sales pipeline forecasts.', 'workcore.crm.forecast', T::object([
                'pipelinePublicId' => $id,
            ])),
            T::read('crm_list_activities', 'List the CRM activity timeline for a lead, customer, contact or opportunity.', 'workcore.crm_activity.list', T::object([
                'subjectType' => T::string(40, ['lead','customer','contact','opportunity']),
                'subjectPublicId' => $id,
                'limit' => T::integer(1, 250),
            ], ['subjectType','subjectPublicId'])),
            T::action('crm_create_customer', 'Create a customer through the governed WorkCore customer action.', 'workcore.customer.create', T::object([
                'name' => T::string(255), 'email' => T::string(320), 'company_name' => T::string(255),
                'phone' => T::string(80), 'address' => T::string(2000), 'note' => $text,
            ], ['name'])),
            T::action('crm_create_lead', 'Create a sales lead through the governed WorkCore lead action.', 'workcore.lead.create', T::object([
                'name' => T::string(255), 'email' => T::string(320), 'company_name' => T::string(255),
                'phone' => T::string(80), 'note' => $text, 'source_public_id' => $id,
                'status_public_id' => $id, 'owner_id' => T::integer(1),
            ], ['name'])),
            T::action('crm_create_opportunity', 'Create a sales opportunity in a selected pipeline stage.', 'workcore.opportunity.create', T::object([
                'pipeline_public_id' => $id, 'stage_public_id' => $id, 'title' => T::string(255),
                'description' => $text, 'amount' => T::number(0), 'currency_code' => T::string(3),
                'probability' => T::integer(0, 100), 'expected_close_date' => T::string(32),
                'lead_public_id' => $id, 'customer_public_id' => $id, 'contact_public_id' => $id,
                'owner_user_id' => T::integer(1), 'metadata' => T::freeObject(),
            ], ['pipeline_public_id','stage_public_id','title'])),
            T::action('crm_move_opportunity', 'Move an opportunity to another stage and board position.', 'workcore.opportunity.move', T::object([
                'opportunity_public_id' => $id, 'stage_public_id' => $id, 'position' => T::integer(0),
            ], ['opportunity_public_id','stage_public_id'])),
            T::action('crm_record_activity', 'Record a note, call, email, meeting or task on a CRM record.', 'workcore.crm_activity.create', T::object([
                'subject_type' => T::string(40, ['lead','customer','contact','opportunity']),
                'subject_public_id' => $id, 'type' => T::string(32, ['note','call','email','meeting','task']),
                'title' => T::string(255), 'body' => $text, 'direction' => T::string(16, ['inbound','outbound']),
                'scheduled_at' => T::string(40), 'completed_at' => T::string(40), 'metadata' => T::freeObject(),
            ], ['subject_type','subject_public_id'])),
        ];
    }
}
