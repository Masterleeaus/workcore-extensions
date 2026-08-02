<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Support\Repositories;

use App\Domains\WorkCore\System\Contracts\TenantContextContract;
use App\Domains\WorkCore\System\Modules\Support\Contracts\SupportTicketRepositoryContract;
use App\Domains\WorkCore\System\Modules\Support\Services\TicketPriorityPolicy;
use App\Domains\WorkCore\System\Modules\Support\Services\TicketStatusPolicy;
use App\Domains\WorkCore\System\Persistence\TenantScopedRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class EloquentSupportTicketRepository extends TenantScopedRepository implements SupportTicketRepositoryContract
{
    public function __construct(
        ConnectionInterface $db,
        TenantContextContract $tenant,
        private TicketStatusPolicy $statusPolicy,
        private TicketPriorityPolicy $priorityPolicy,
    ) {
        parent::__construct($db, $tenant);
    }

    public function search(int $companyId, array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        $this->assertCompany($companyId);
        $query = $this->baseQuery($companyId);
        if ($q = trim((string) ($filters['q'] ?? ''))) {
            $query->where(function (Builder $builder) use ($q): void {
                $builder->where('tickets.ticket_number', 'like', "%{$q}%")
                    ->orWhere('tickets.subject', 'like', "%{$q}%")
                    ->orWhere('tickets.description', 'like', "%{$q}%")
                    ->orWhere('tickets.requester_name', 'like', "%{$q}%")
                    ->orWhere('tickets.requester_email', 'like', "%{$q}%");
            });
        }
        foreach (['status' => 'tickets.status', 'priority' => 'tickets.priority', 'source' => 'tickets.source', 'category' => 'tickets.category'] as $filter => $column) {
            if (! empty($filters[$filter])) {
                $query->where($column, $filters[$filter]);
            }
        }
        if (! empty($filters['queue_public_id'])) {
            $query->where('queues.public_id', $filters['queue_public_id']);
        }
        if (! empty($filters['assigned_worker_public_id'])) {
            $query->where('workers.public_id', $filters['assigned_worker_public_id']);
        }
        if (! empty($filters['premises_public_id'])) {
            $query->where('premises.public_id', $filters['premises_public_id']);
        }
        if (! empty($filters['customer_public_id'])) {
            $query->where('customers.public_id', $filters['customer_public_id']);
        }
        if (! empty($filters['overdue'])) {
            $query->whereNotIn('tickets.status', ['resolved', 'closed', 'cancelled'])
                ->where(function (Builder $builder): void {
                    $builder->where('tickets.first_response_due_at', '<', now())
                        ->whereNull('tickets.first_responded_at')
                        ->orWhere('tickets.resolution_due_at', '<', now());
                });
        }
        return $query->orderByRaw("CASE tickets.priority WHEN 'emergency' THEN 1 WHEN 'urgent' THEN 2 WHEN 'high' THEN 3 WHEN 'normal' THEN 4 ELSE 5 END")
            ->orderByDesc('tickets.last_activity_at')
            ->paginate(max(1, min($perPage, 100)));
    }

    public function createQueue(array $data, int $companyId, int $actorId): array
    {
        $this->assertActor($companyId, $actorId);
        $code = Str::lower(trim((string) $data['code']));
        if ($this->db->table('tz_support_queues')->where('company_id', $companyId)->where('code', $code)->whereNull('deleted_at')->exists()) {
            throw new InvalidArgumentException('Support queue code already exists for the active company.');
        }
        $priority = (string) ($data['default_priority'] ?? 'normal');
        $this->priorityPolicy->assertValid($priority);
        $publicId = (string) Str::ulid();
        $this->db->table('tz_support_queues')->insert([
            'public_id' => $publicId,
            'company_id' => $companyId,
            'code' => $code,
            'name' => (string) $data['name'],
            'description' => $data['description'] ?? null,
            'default_priority' => $priority,
            'first_response_minutes' => (int) ($data['first_response_minutes'] ?? $this->priorityPolicy->defaultDueMinutes($priority)),
            'resolution_minutes' => (int) ($data['resolution_minutes'] ?? max(60, $this->priorityPolicy->defaultDueMinutes($priority) * 4)),
            'channel_defaults' => isset($data['channel_defaults']) ? json_encode($data['channel_defaults'], JSON_THROW_ON_ERROR) : null,
            'active' => (bool) ($data['active'] ?? true),
            'created_by_user_id' => $actorId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        return (array) $this->db->table('tz_support_queues')->where('company_id', $companyId)->where('public_id', $publicId)->first();
    }

    public function createTicket(array $data, int $companyId, int $actorId): array
    {
        $this->assertActor($companyId, $actorId);
        $queue = $this->optionalRecord('tz_support_queues', $companyId, $data['queue_public_id'] ?? null, 'Support queue');
        $customer = $this->optionalRecord('tz_customers', $companyId, $data['customer_public_id'] ?? null, 'Customer');
        $contact = $this->optionalRecord('tz_customer_contacts', $companyId, $data['contact_public_id'] ?? null, 'Contact');
        if ($contact && $customer && (int) $contact->customer_id !== (int) $customer->id) {
            throw new InvalidArgumentException('Contact does not belong to the selected customer.');
        }
        if (! $customer && $contact) {
            $customer = $this->db->table('tz_customers')->where('company_id', $companyId)->where('id', $contact->customer_id)->first();
        }
        $premises = $this->optionalRecord('tz_premises', $companyId, $data['premises_public_id'] ?? null, 'Premises');
        if ($premises && $customer && (int) $premises->customer_id !== (int) $customer->id) {
            throw new InvalidArgumentException('Premises does not belong to the selected customer.');
        }
        $workOrder = $this->optionalRecord('tz_work_orders', $companyId, $data['work_order_public_id'] ?? null, 'Work Order');
        $appointment = $this->optionalRecord('tz_appointments', $companyId, $data['appointment_public_id'] ?? null, 'Appointment');
        $asset = $this->optionalRecord('tz_assets', $companyId, $data['asset_public_id'] ?? null, 'Asset');
        $assignedWorker = $this->optionalRecord('tz_workers', $companyId, $data['assigned_worker_public_id'] ?? null, 'Assigned worker');

        if ($queue && ! (bool) $queue->active) {
            throw new InvalidArgumentException('Support queue is inactive.');
        }
        $priority = (string) ($data['priority'] ?? ($queue->default_priority ?? 'normal'));
        $this->priorityPolicy->assertValid($priority);
        $status = $assignedWorker ? 'assigned' : (string) ($data['status'] ?? 'open');
        if (! in_array($status, ['open', 'triaged', 'assigned'], true)) {
            throw new InvalidArgumentException('New support tickets must start open, triaged or assigned.');
        }
        $responseMinutes = (int) ($data['first_response_minutes'] ?? ($queue->first_response_minutes ?? $this->priorityPolicy->defaultDueMinutes($priority)));
        $resolutionMinutes = (int) ($data['resolution_minutes'] ?? ($queue->resolution_minutes ?? max(60, $responseMinutes * 4)));
        $publicId = (string) Str::ulid();
        $ticketNumber = trim((string) ($data['ticket_number'] ?? '')) ?: 'SUP-' . now()->format('ymd') . '-' . Str::upper(substr($publicId, -6));
        if ($this->db->table('tz_support_tickets')->where('company_id', $companyId)->where('ticket_number', $ticketNumber)->exists()) {
            throw new InvalidArgumentException('Support ticket number already exists for the active company.');
        }

        return $this->db->transaction(function () use ($data, $companyId, $actorId, $queue, $customer, $contact, $premises, $workOrder, $appointment, $asset, $assignedWorker, $priority, $status, $responseMinutes, $resolutionMinutes, $publicId, $ticketNumber): array {
            $ticketId = $this->db->table('tz_support_tickets')->insertGetId([
                'public_id' => $publicId,
                'company_id' => $companyId,
                'queue_id' => $queue?->id,
                'customer_id' => $customer?->id,
                'contact_id' => $contact?->id,
                'premises_id' => $premises?->id,
                'work_order_id' => $workOrder?->id,
                'appointment_id' => $appointment?->id,
                'asset_id' => $asset?->id,
                'requester_user_id' => ($data['requester_type'] ?? 'external') === 'internal' ? $actorId : null,
                'assigned_worker_id' => $assignedWorker?->id,
                'ticket_number' => $ticketNumber,
                'subject' => (string) $data['subject'],
                'description' => (string) $data['description'],
                'category' => (string) ($data['category'] ?? 'general'),
                'source' => (string) ($data['source'] ?? 'manual'),
                'requester_type' => (string) ($data['requester_type'] ?? 'external'),
                'requester_name' => $data['requester_name'] ?? ($contact->name ?? null),
                'requester_email' => $data['requester_email'] ?? ($contact->email ?? null),
                'requester_phone' => $data['requester_phone'] ?? ($contact->phone ?? null),
                'priority' => $priority,
                'status' => $status,
                'first_response_due_at' => now()->copy()->addMinutes(max(1, $responseMinutes)),
                'resolution_due_at' => now()->copy()->addMinutes(max(1, $resolutionMinutes)),
                'last_activity_at' => now(),
                'escalation_level' => $this->priorityPolicy->isEmergency($priority) ? 1 : 0,
                'confidential' => (bool) ($data['confidential'] ?? false),
                'metadata' => isset($data['metadata']) ? json_encode($data['metadata'], JSON_THROW_ON_ERROR) : null,
                'created_by_user_id' => $actorId,
                'updated_by_user_id' => $actorId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->appendStatusHistory($companyId, $ticketId, null, $status, 'Support ticket created.', $actorId);
            if ($assignedWorker) {
                $this->createAssignment($companyId, $ticketId, (int) $assignedWorker->id, $actorId, 'Assigned during ticket creation.');
            }
            $this->db->table('tz_support_ticket_messages')->insert([
                'public_id' => (string) Str::ulid(),
                'company_id' => $companyId,
                'support_ticket_id' => $ticketId,
                'author_user_id' => $actorId,
                'author_contact_id' => $contact?->id,
                'message_type' => 'request',
                'visibility' => 'public',
                'channel' => (string) ($data['source'] ?? 'manual'),
                'body' => (string) $data['description'],
                'sent_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            return $this->findByPublicId($companyId, $publicId) ?? ['public_id' => $publicId];
        }, 3);
    }

    public function addMessage(string $ticketPublicId, array $data, int $companyId, int $actorId): array
    {
        $this->assertActor($companyId, $actorId);
        return $this->db->transaction(function () use ($ticketPublicId, $data, $companyId, $actorId): array {
            $ticket = $this->ticketForUpdate($companyId, $ticketPublicId);
            $worker = $this->optionalRecord('tz_workers', $companyId, $data['author_worker_public_id'] ?? null, 'Author worker');
            $contact = $this->optionalRecord('tz_customer_contacts', $companyId, $data['author_contact_public_id'] ?? null, 'Author contact');
            $messageType = (string) ($data['message_type'] ?? 'reply');
            if (! in_array($messageType, ['reply', 'internal_note', 'system', 'request'], true)) {
                throw new InvalidArgumentException('Unsupported support ticket message type.');
            }
            $visibility = (string) ($data['visibility'] ?? ($messageType === 'internal_note' ? 'internal' : 'public'));
            if (! in_array($visibility, ['public', 'internal'], true)) {
                throw new InvalidArgumentException('Unsupported support ticket message visibility.');
            }
            $publicId = (string) Str::ulid();
            $this->db->table('tz_support_ticket_messages')->insert([
                'public_id' => $publicId,
                'company_id' => $companyId,
                'support_ticket_id' => $ticket->id,
                'author_user_id' => $actorId,
                'author_worker_id' => $worker?->id,
                'author_contact_id' => $contact?->id,
                'message_type' => $messageType,
                'visibility' => $visibility,
                'channel' => (string) ($data['channel'] ?? 'internal'),
                'body' => (string) $data['body'],
                'attachments' => isset($data['attachments']) ? json_encode($data['attachments'], JSON_THROW_ON_ERROR) : null,
                'sent_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $updates = ['last_activity_at' => now(), 'updated_by_user_id' => $actorId, 'updated_at' => now()];
            if ($visibility === 'public' && $messageType === 'reply' && $ticket->first_responded_at === null) {
                $updates['first_responded_at'] = now();
            }
            $this->db->table('tz_support_tickets')->where('id', $ticket->id)->update($updates);
            return (array) $this->db->table('tz_support_ticket_messages')->where('company_id', $companyId)->where('public_id', $publicId)->first();
        }, 3);
    }

    public function assign(string $ticketPublicId, array $data, int $companyId, int $actorId): array
    {
        $this->assertActor($companyId, $actorId);
        return $this->db->transaction(function () use ($ticketPublicId, $data, $companyId, $actorId): array {
            $ticket = $this->ticketForUpdate($companyId, $ticketPublicId);
            $worker = $this->optionalRecord('tz_workers', $companyId, $data['worker_public_id'] ?? null, 'Assigned worker');
            if (! $worker) {
                throw new InvalidArgumentException('An active company worker is required.');
            }
            if (! in_array((string) ($worker->employment_status ?? 'active'), ['active', 'onboarding'], true)) {
                throw new InvalidArgumentException('Assigned worker is not operationally active.');
            }
            $this->db->table('tz_support_ticket_assignments')->where('company_id', $companyId)->where('support_ticket_id', $ticket->id)->where('active', true)->update(['active' => false, 'ended_at' => now(), 'updated_at' => now()]);
            $this->createAssignment($companyId, (int) $ticket->id, (int) $worker->id, $actorId, $data['reason'] ?? null);
            $updates = ['assigned_worker_id' => $worker->id, 'updated_by_user_id' => $actorId, 'last_activity_at' => now(), 'updated_at' => now()];
            if (in_array((string) $ticket->status, ['open', 'triaged'], true)) {
                $updates['status'] = 'assigned';
                $this->appendStatusHistory($companyId, (int) $ticket->id, (string) $ticket->status, 'assigned', $data['reason'] ?? 'Ticket assigned.', $actorId);
            }
            $this->db->table('tz_support_tickets')->where('id', $ticket->id)->update($updates);
            return $this->findByPublicId($companyId, $ticketPublicId) ?? ['public_id' => $ticketPublicId];
        }, 3);
    }

    public function changeStatus(string $ticketPublicId, array $data, int $companyId, int $actorId): array
    {
        $this->assertActor($companyId, $actorId);
        return $this->db->transaction(function () use ($ticketPublicId, $data, $companyId, $actorId): array {
            $ticket = $this->ticketForUpdate($companyId, $ticketPublicId);
            $to = (string) $data['status'];
            $reason = isset($data['reason']) ? trim((string) $data['reason']) : null;
            $this->statusPolicy->assertTransition((string) $ticket->status, $to, $reason);
            if ((string) $ticket->status === $to) {
                return $this->findByPublicId($companyId, $ticketPublicId) ?? ['public_id' => $ticketPublicId];
            }
            $updates = ['status' => $to, 'last_activity_at' => now(), 'updated_by_user_id' => $actorId, 'updated_at' => now()];
            if ($to === 'resolved') {
                $updates['resolved_at'] = now();
            }
            if ($to === 'closed') {
                $updates['closed_at'] = now();
            }
            if (in_array((string) $ticket->status, ['resolved', 'closed'], true) && $to === 'in_progress') {
                $updates['resolved_at'] = null;
                $updates['closed_at'] = null;
            }
            if ((string) $ticket->status === 'cancelled' && $to === 'open') {
                $updates['closed_at'] = null;
            }
            $this->db->table('tz_support_tickets')->where('id', $ticket->id)->update($updates);
            $this->appendStatusHistory($companyId, (int) $ticket->id, (string) $ticket->status, $to, $reason, $actorId);
            return $this->findByPublicId($companyId, $ticketPublicId) ?? ['public_id' => $ticketPublicId];
        }, 3);
    }

    public function findByPublicId(int $companyId, string $publicId): ?array
    {
        $this->assertCompany($companyId);
        $row = $this->baseQuery($companyId)->where('tickets.public_id', $publicId)->first();
        if (! $row) {
            return null;
        }
        $record = (array) $row;
        $ticketId = (int) $record['internal_id'];
        unset($record['internal_id']);
        $record['messages'] = $this->db->table('tz_support_ticket_messages as messages')
            ->leftJoin('tz_workers as workers', 'workers.id', '=', 'messages.author_worker_id')
            ->leftJoin('tz_customer_contacts as contacts', 'contacts.id', '=', 'messages.author_contact_id')
            ->where('messages.company_id', $companyId)
            ->where('messages.support_ticket_id', $ticketId)
            ->orderBy('messages.sent_at')
            ->get(['messages.public_id', 'messages.message_type', 'messages.visibility', 'messages.channel', 'messages.body', 'messages.attachments', 'messages.sent_at', 'workers.public_id as author_worker_public_id', 'workers.display_name as author_worker_name', 'contacts.public_id as author_contact_public_id', 'contacts.name as author_contact_name'])
            ->map(static fn (object $item): array => (array) $item)->all();
        return $record;
    }

    public function linkWorkOrder(string $ticketPublicId, string $workOrderPublicId, int $companyId, int $actorId): array
    {
        $this->assertActor($companyId, $actorId);
        $ticket = $this->ticketForUpdate($companyId, $ticketPublicId);
        $workOrder = $this->optionalRecord('tz_work_orders', $companyId, $workOrderPublicId, 'Work Order');
        if (! $workOrder) {
            throw new InvalidArgumentException('Work Order does not belong to the active company.');
        }
        $this->db->table('tz_support_tickets')->where('id', $ticket->id)->update(['work_order_id' => $workOrder->id, 'last_activity_at' => now(), 'updated_by_user_id' => $actorId, 'updated_at' => now()]);
        return $this->findByPublicId($companyId, $ticketPublicId) ?? ['public_id' => $ticketPublicId];
    }

    private function baseQuery(int $companyId): Builder
    {
        return $this->db->table('tz_support_tickets as tickets')
            ->leftJoin('tz_support_queues as queues', 'queues.id', '=', 'tickets.queue_id')
            ->leftJoin('tz_customers as customers', 'customers.id', '=', 'tickets.customer_id')
            ->leftJoin('tz_customer_contacts as contacts', 'contacts.id', '=', 'tickets.contact_id')
            ->leftJoin('tz_premises as premises', 'premises.id', '=', 'tickets.premises_id')
            ->leftJoin('tz_work_orders as work_orders', 'work_orders.id', '=', 'tickets.work_order_id')
            ->leftJoin('tz_appointments as appointments', 'appointments.id', '=', 'tickets.appointment_id')
            ->leftJoin('tz_assets as assets', 'assets.id', '=', 'tickets.asset_id')
            ->leftJoin('tz_workers as workers', 'workers.id', '=', 'tickets.assigned_worker_id')
            ->where('tickets.company_id', $companyId)
            ->whereNull('tickets.deleted_at')
            ->select([
                'tickets.id as internal_id', 'tickets.public_id', 'tickets.ticket_number', 'tickets.subject', 'tickets.description', 'tickets.category', 'tickets.source', 'tickets.requester_type', 'tickets.requester_name', 'tickets.requester_email', 'tickets.requester_phone', 'tickets.priority', 'tickets.status', 'tickets.first_response_due_at', 'tickets.resolution_due_at', 'tickets.first_responded_at', 'tickets.resolved_at', 'tickets.closed_at', 'tickets.last_activity_at', 'tickets.escalation_level', 'tickets.confidential', 'tickets.metadata', 'tickets.created_at', 'tickets.updated_at',
                'queues.public_id as queue_public_id', 'queues.name as queue_name',
                'customers.public_id as customer_public_id', 'customers.name as customer_name',
                'contacts.public_id as contact_public_id', 'contacts.name as contact_name',
                'premises.public_id as premises_public_id', 'premises.name as premises_name',
                'work_orders.public_id as work_order_public_id', 'work_orders.number as work_order_number',
                'appointments.public_id as appointment_public_id', 'appointments.title as appointment_title',
                'assets.public_id as asset_public_id', 'assets.name as asset_name',
                'workers.public_id as assigned_worker_public_id', 'workers.display_name as assigned_worker_name',
            ]);
    }

    private function ticketForUpdate(int $companyId, string $publicId): object
    {
        $ticket = $this->db->table('tz_support_tickets')->where('company_id', $companyId)->where('public_id', $publicId)->whereNull('deleted_at')->lockForUpdate()->first();
        if (! $ticket) {
            throw new InvalidArgumentException('Support ticket does not belong to the active company.');
        }
        return $ticket;
    }

    private function optionalRecord(string $table, int $companyId, mixed $publicId, string $label): ?object
    {
        if ($publicId === null || $publicId === '') {
            return null;
        }
        $query = $this->db->table($table)->where('company_id', $companyId)->where('public_id', (string) $publicId);
        if (in_array($table, ['tz_support_queues', 'tz_customers', 'tz_customer_contacts', 'tz_premises', 'tz_work_orders', 'tz_appointments', 'tz_assets', 'tz_workers'], true)) {
            $query->whereNull('deleted_at');
        }
        $record = $query->first();
        if (! $record) {
            throw new InvalidArgumentException("{$label} does not belong to the active company.");
        }
        return $record;
    }

    private function createAssignment(int $companyId, int $ticketId, int $workerId, int $actorId, ?string $reason): void
    {
        $this->db->table('tz_support_ticket_assignments')->insert([
            'public_id' => (string) Str::ulid(),
            'company_id' => $companyId,
            'support_ticket_id' => $ticketId,
            'worker_id' => $workerId,
            'assigned_by_user_id' => $actorId,
            'assigned_at' => now(),
            'reason' => $reason,
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function appendStatusHistory(int $companyId, int $ticketId, ?string $from, string $to, ?string $reason, int $actorId): void
    {
        $this->db->table('tz_support_ticket_status_history')->insert([
            'public_id' => (string) Str::ulid(),
            'company_id' => $companyId,
            'support_ticket_id' => $ticketId,
            'from_status' => $from,
            'to_status' => $to,
            'reason' => $reason,
            'actor_user_id' => $actorId,
            'changed_at' => now(),
            'created_at' => now(),
        ]);
    }

    private function assertActor(int $companyId, int $actorId): void
    {
        $this->assertCompany($companyId);
        if ($actorId <= 0) {
            throw new InvalidArgumentException('Support ticket actions require an authenticated MagicAI user.');
        }
    }
}
