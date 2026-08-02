<?php

declare(strict_types=1);
namespace App\Domains\WorkCore\System\Contracts\Records\Adapters;
use App\Domains\WorkCore\System\Contracts\Records\SupportTicketAccessContract;
final class SupportTicketAccessAdapter extends AbstractDatabaseRecordAccess implements SupportTicketAccessContract
{
    protected function table(): string{return 'tz_support_tickets';}
    protected function type(): string{return 'support_ticket';}
    protected function columns(): array{return ['public_id','queue_id','customer_id','contact_id','premises_id','work_order_id','appointment_id','asset_id','assigned_worker_id','ticket_number','subject','description','category','source','requester_type','requester_name','requester_email','requester_phone','priority','status','first_response_due_at','resolution_due_at','first_responded_at','resolved_at','closed_at','last_activity_at','escalation_level','confidential','metadata','created_at','updated_at'];}
}
