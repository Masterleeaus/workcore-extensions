<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\AI\Orchestration\Persistence;

use App\Domains\WorkCore\System\AI\Orchestration\Contracts\ApprovalStoreContract;
use App\Domains\WorkCore\System\AI\Orchestration\DTO\{ApprovalRecord,OrchestrationRunRecord,ToolCall};
use App\Domains\WorkCore\System\AI\Orchestration\Exceptions\AiOrchestrationException;
use App\Domains\WorkCore\System\AI\Tools\AiToolDefinition;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

final class DatabaseApprovalStore implements ApprovalStoreContract
{
    public function __construct(private ConnectionInterface $db) {}

    public function create(OrchestrationRunRecord $run, ToolCall $call, AiToolDefinition $tool, int $expiresInMinutes): ApprovalRecord
    {
        return $this->db->transaction(function () use ($run, $call, $tool, $expiresInMinutes): ApprovalRecord {
            $ids = $this->db->table('tz_ai_orchestration_runs as r')
                ->join('tz_ai_conversations as c', function ($join): void {
                    $join->on('c.id', '=', 'r.conversation_id')->on('c.company_id', '=', 'r.company_id');
                })
                ->where('r.company_id', $run->companyId)
                ->where('r.public_id', $run->publicId)
                ->first(['r.id as run_id','c.id as conversation_id']);
            if ($ids === null) {
                throw new AiOrchestrationException('Cannot create an approval for a missing AI run.', 404);
            }

            $publicId = (string) Str::ulid();
            $expiresAt = now()->addMinutes(max(1, $expiresInMinutes));
            $now = now();
            $this->db->table('tz_ai_approval_requests')->insert([
                'public_id' => $publicId,
                'company_id' => $run->companyId,
                'orchestration_run_id' => (int) $ids->run_id,
                'conversation_id' => (int) $ids->conversation_id,
                'requested_by_user_id' => $run->actorId,
                'tool_call_id' => $call->id,
                'tool_name' => $tool->name,
                'tool_input' => json_encode($call->arguments, JSON_THROW_ON_ERROR),
                'risk' => $tool->risk,
                'status' => 'pending',
                'expires_at' => $expiresAt,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            return new ApprovalRecord(
                $publicId,
                $run->companyId,
                $run->actorId,
                $run->publicId,
                $run->conversationPublicId,
                $call->id,
                $tool->name,
                $call->arguments,
                $tool->risk,
                'pending',
                null,
                null,
                false,
                $expiresAt->getTimestamp(),
            );
        }, 3);
    }

    public function find(int $companyId, string $approvalPublicId): ?ApprovalRecord
    {
        $row = $this->db->table('tz_ai_approval_requests as a')
            ->join('tz_ai_orchestration_runs as r', function ($join): void {
                $join->on('r.id', '=', 'a.orchestration_run_id')->on('r.company_id', '=', 'a.company_id');
            })
            ->join('tz_ai_conversations as c', function ($join): void {
                $join->on('c.id', '=', 'a.conversation_id')->on('c.company_id', '=', 'a.company_id');
            })
            ->where('a.company_id', $companyId)
            ->where('a.public_id', $approvalPublicId)
            ->first(['a.*','r.public_id as run_public_id','c.public_id as conversation_public_id']);
        return $row === null ? null : $this->map($row);
    }

    public function decide(int $companyId, string $approvalPublicId, int $actorId, string $decision, ?string $note = null): ApprovalRecord
    {
        if (! in_array($decision, ['approve', 'reject'], true)) {
            throw new AiOrchestrationException('Approval decision must be approve or reject.', 422);
        }

        return $this->db->transaction(function () use ($companyId, $approvalPublicId, $actorId, $decision, $note): ApprovalRecord {
            $row = $this->db->table('tz_ai_approval_requests')
                ->where('company_id', $companyId)
                ->where('public_id', $approvalPublicId)
                ->lockForUpdate()
                ->first();
            if ($row === null) {
                throw new AiOrchestrationException('The AI approval request was not found.', 404);
            }
            if ((string) $row->status !== 'pending') {
                throw new AiOrchestrationException('The AI approval request has already been decided.', 409);
            }
            if (now()->greaterThanOrEqualTo(Carbon::parse((string) $row->expires_at))) {
                $this->db->table('tz_ai_approval_requests')->where('id', $row->id)->update(['status' => 'expired', 'updated_at' => now()]);
                throw new AiOrchestrationException('The AI approval request has expired.', 410);
            }

            $this->db->table('tz_ai_approval_requests')->where('id', $row->id)->update([
                'status' => $decision === 'approve' ? 'approved' : 'rejected',
                'decided_by_user_id' => $actorId,
                'decided_at' => now(),
                'decision_note' => $note,
                'updated_at' => now(),
            ]);

            return $this->find($companyId, $approvalPublicId)
                ?? throw new AiOrchestrationException('The AI approval decision could not be reloaded.', 500);
        }, 3);
    }

    public function markConsumed(int $companyId, string $approvalPublicId): void
    {
        $updated = $this->db->table('tz_ai_approval_requests')
            ->where('company_id', $companyId)
            ->where('public_id', $approvalPublicId)
            ->where('status', 'approved')
            ->whereNull('consumed_at')
            ->update(['consumed_at' => now(), 'updated_at' => now()]);
        if ($updated !== 1) {
            throw new AiOrchestrationException('The AI approval is not available for one-time consumption.', 409);
        }
    }

    private function map(object $row): ApprovalRecord
    {
        $expiresAt = $row->expires_at instanceof \DateTimeInterface
            ? $row->expires_at->getTimestamp()
            : strtotime((string) $row->expires_at);
        return new ApprovalRecord(
            (string) $row->public_id,
            (int) $row->company_id,
            (int) $row->requested_by_user_id,
            (string) $row->run_public_id,
            (string) $row->conversation_public_id,
            (string) $row->tool_call_id,
            (string) $row->tool_name,
            $this->decode($row->tool_input),
            (string) $row->risk,
            (string) $row->status,
            $row->decided_by_user_id === null ? null : (int) $row->decided_by_user_id,
            $row->decision_note === null ? null : (string) $row->decision_note,
            $row->consumed_at !== null,
            (int) $expiresAt,
        );
    }

    /** @return array<string,mixed> */
    private function decode(mixed $value): array
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            return is_array($decoded) ? $decoded : [];
        }
        return is_array($value) ? $value : [];
    }
}
