<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\TrustAccounting\Repositories;

use App\Domains\WorkCore\System\Contracts\TenantContextContract;
use App\Domains\WorkCore\System\Modules\TrustAccounting\Contracts\TrustAccountingRepositoryContract;
use App\Domains\WorkCore\System\Modules\TrustAccounting\Domain\TrustLedgerPolicy;
use App\Domains\WorkCore\System\Persistence\TenantScopedRepository;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class EloquentTrustAccountingRepository extends TenantScopedRepository implements TrustAccountingRepositoryContract
{
    public function __construct(ConnectionInterface $db, TenantContextContract $tenant) { parent::__construct($db, $tenant); }

    public function createTrustAccount(array $data, int $companyId, int $actorId): array
    {
        $this->guard($companyId, $actorId);
        $isOperating = (bool) ($data['is_operating_account'] ?? false);
        $type = strtolower((string) ($data['account_type'] ?? 'trust'));
        if (! TrustLedgerPolicy::mayPostReceipt($isOperating, $type)) throw new InvalidArgumentException('A trust account cannot be an operating account.');
        $publicId = (string) Str::ulid();
        $this->db->table('tz_trust_accounts')->insert([
            'public_id' => $publicId, 'company_id' => $companyId, 'name' => trim((string) ($data['name'] ?? 'Trust Account')),
            'account_type' => 'trust', 'currency' => strtoupper((string) ($data['currency'] ?? 'AUD')),
            'bank_reference' => $data['bank_reference'] ?? null, 'credential_reference' => $data['credential_reference'] ?? null,
            'is_operating_account' => false, 'required_approvals' => max(1, (int) ($data['required_approvals'] ?? 2)),
            'active' => true, 'metadata' => $this->json($data['metadata'] ?? null), 'created_at' => now(), 'updated_at' => now(),
        ]);
        return $this->snapshot('tz_trust_accounts', $companyId, $publicId);
    }

    public function createMatter(array $data, int $companyId, int $actorId): array
    {
        $this->guard($companyId, $actorId);
        $account = $this->record('tz_trust_accounts', $companyId, (string) ($data['trust_account_public_id'] ?? ''));
        if (! $account->active || $account->is_operating_account) throw new InvalidArgumentException('Trust account is not active.');
        $publicId = (string) Str::ulid();
        $number = trim((string) ($data['matter_number'] ?? '')) ?: 'TRM-'.now()->format('ymd').'-'.Str::upper(substr($publicId, -6));
        $this->db->table('tz_trust_matters')->insert([
            'public_id' => $publicId, 'company_id' => $companyId, 'trust_account_id' => $account->id,
            'customer_id' => $this->optionalId('tz_customers', $companyId, $data['customer_public_id'] ?? null),
            'premises_id' => $this->optionalId('tz_premises', $companyId, $data['premises_public_id'] ?? null),
            'agreement_public_id' => $this->optionalPublicId($data['agreement_public_id'] ?? null),
            'matter_number' => $number, 'matter_type' => $data['matter_type'] ?? 'client_money', 'status' => 'open',
            'display_name' => trim((string) ($data['display_name'] ?? $number)), 'metadata' => $this->json($data['metadata'] ?? null),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        return $this->snapshot('tz_trust_matters', $companyId, $publicId);
    }

    public function recordReceipt(array $data, int $companyId, int $actorId): array
    {
        $this->guard($companyId, $actorId);
        $account = $this->record('tz_trust_accounts', $companyId, (string) ($data['trust_account_public_id'] ?? ''));
        if (! TrustLedgerPolicy::mayPostReceipt((bool) $account->is_operating_account, (string) $account->account_type)) throw new InvalidArgumentException('Trust receipt destination is invalid.');
        $matter = isset($data['matter_public_id']) ? $this->record('tz_trust_matters', $companyId, (string) $data['matter_public_id']) : null;
        if ($matter && (int) $matter->trust_account_id !== (int) $account->id) throw new InvalidArgumentException('Trust matter belongs to another trust account.');
        $amount = (int) ($data['amount_minor'] ?? 0); if ($amount <= 0) throw new InvalidArgumentException('Trust receipt amount must be positive.');
        return $this->db->transaction(function () use ($data, $account, $matter, $amount, $companyId, $actorId): array {
            $publicId = (string) Str::ulid(); $number = trim((string) ($data['receipt_number'] ?? '')) ?: 'TR-'.now()->format('ymdHis');
            $this->db->table('tz_trust_receipts')->insert([
                'public_id' => $publicId, 'company_id' => $companyId, 'trust_account_id' => $account->id,
                'matter_id' => $matter?->id, 'receipt_number' => $number, 'amount_minor' => $amount,
                'currency' => $data['currency'] ?? $account->currency, 'payer_name' => trim((string) ($data['payer_name'] ?? 'Unknown payer')),
                'reference' => $data['reference'] ?? null, 'received_at' => $data['received_at'] ?? now(), 'status' => 'received',
                'source_observation_public_id' => $data['source_observation_public_id'] ?? null, 'created_by_user_id' => $actorId,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $this->appendLedger((int) $account->id, $matter?->id, 'receipt', 'trust_receipt', $publicId, $amount, (string) ($data['currency'] ?? $account->currency), null, $data['description'] ?? 'Trust receipt', $companyId, $actorId);
            return $this->snapshot('tz_trust_receipts', $companyId, $publicId);
        }, 3);
    }

    public function allocateReceipt(string $receiptPublicId, string $matterPublicId, array $data, int $companyId, int $actorId): array
    {
        $this->guard($companyId, $actorId);
        $receipt = $this->record('tz_trust_receipts', $companyId, $receiptPublicId);
        $matter = $this->record('tz_trust_matters', $companyId, $matterPublicId);
        if ((int) $receipt->trust_account_id !== (int) $matter->trust_account_id) throw new InvalidArgumentException('Receipt and matter use different trust accounts.');
        if ($receipt->matter_id !== null && (int) $receipt->matter_id !== (int) $matter->id) {
            throw new InvalidArgumentException('A matter-specific receipt cannot be allocated to another matter.');
        }
        $amount = (int) ($data['amount_minor'] ?? 0); if ($amount <= 0) throw new InvalidArgumentException('Allocation amount must be positive.');
        return $this->db->transaction(function () use ($receipt, $matter, $amount, $data, $companyId, $actorId): array {
            $allocated = (int) $this->db->table('tz_trust_allocations')->where('company_id', $companyId)->where('receipt_id', $receipt->id)->sum('amount_minor');
            if ($allocated + $amount > (int) $receipt->amount_minor) throw new InvalidArgumentException('Trust allocation exceeds receipt balance.');
            $publicId = (string) Str::ulid();
            $this->db->table('tz_trust_allocations')->insert([
                'public_id' => $publicId, 'company_id' => $companyId, 'receipt_id' => $receipt->id, 'matter_id' => $matter->id,
                'amount_minor' => $amount, 'currency' => $receipt->currency, 'reversal_of_allocation_id' => null,
                'purpose' => $data['purpose'] ?? null, 'created_by_user_id' => $actorId, 'created_at' => now(), 'updated_at' => now(),
            ]);
            if ($receipt->matter_id === null) {
                $this->appendLedger((int) $receipt->trust_account_id, null, 'allocation_transfer_out', 'trust_allocation', $publicId,
                    -$amount, (string) $receipt->currency, null, $data['purpose'] ?? 'Allocate unassigned trust receipt', $companyId, $actorId);
                $this->appendLedger((int) $receipt->trust_account_id, (int) $matter->id, 'allocation_transfer_in', 'trust_allocation', $publicId,
                    $amount, (string) $receipt->currency, null, $data['purpose'] ?? 'Allocate trust receipt to matter', $companyId, $actorId);
            }
            return $this->snapshot('tz_trust_allocations', $companyId, $publicId);
        }, 3);
    }

    public function requestDisbursement(array $data, int $companyId, int $actorId): array
    {
        $this->guard($companyId, $actorId);
        $account = $this->record('tz_trust_accounts', $companyId, (string) ($data['trust_account_public_id'] ?? ''));
        $matter = $this->record('tz_trust_matters', $companyId, (string) ($data['matter_public_id'] ?? ''));
        if ((int) $matter->trust_account_id !== (int) $account->id) throw new InvalidArgumentException('Trust matter account mismatch.');
        $amount = (int) ($data['amount_minor'] ?? 0); if ($amount <= 0 || $amount > $this->matterBalance((int) $matter->id, $companyId)) throw new InvalidArgumentException('Trust disbursement exceeds available matter funds.');
        $publicId = (string) Str::ulid(); $number = trim((string) ($data['disbursement_number'] ?? '')) ?: 'TD-'.now()->format('ymdHis');
        $this->db->table('tz_trust_disbursements')->insert([
            'public_id' => $publicId, 'company_id' => $companyId, 'trust_account_id' => $account->id, 'matter_id' => $matter->id,
            'disbursement_number' => $number, 'amount_minor' => $amount, 'currency' => $account->currency,
            'payee_name' => trim((string) ($data['payee_name'] ?? '')), 'status' => 'pending_approval',
            'required_approvals' => max(1, (int) $account->required_approvals), 'purpose' => trim((string) ($data['purpose'] ?? '')),
            'destination_reference' => $data['destination_reference'] ?? null, 'requested_by_user_id' => $actorId,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        return $this->snapshot('tz_trust_disbursements', $companyId, $publicId);
    }

    public function approveDisbursement(string $publicId, array $data, int $companyId, int $actorId): array
    {
        $this->guard($companyId, $actorId);
        $disbursement = $this->record('tz_trust_disbursements', $companyId, $publicId);
        if ((int) $disbursement->requested_by_user_id === $actorId) throw new InvalidArgumentException('Requester cannot approve their own trust disbursement.');
        if (! in_array($disbursement->status, ['pending_approval', 'approved'], true)) throw new InvalidArgumentException('Trust disbursement is not awaiting approval.');
        $decision = strtolower((string) ($data['decision'] ?? 'approved'));
        if (! in_array($decision, ['approved', 'rejected'], true)) throw new InvalidArgumentException('Invalid trust approval decision.');
        $existing = $this->db->table('tz_trust_approvals')->where('company_id', $companyId)->where('disbursement_id', $disbursement->id)->where('approved_by_user_id', $actorId)->first();
        if (! $existing) $this->db->table('tz_trust_approvals')->insert([
            'company_id' => $companyId, 'disbursement_id' => $disbursement->id, 'approved_by_user_id' => $actorId,
            'decision' => $decision, 'reason' => $data['reason'] ?? null, 'decided_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        $approved = $this->db->table('tz_trust_approvals')->where('company_id', $companyId)->where('disbursement_id', $disbursement->id)->where('decision', 'approved')->count();
        $status = $decision === 'rejected' ? 'rejected' : (TrustLedgerPolicy::mayReleaseDisbursement($approved, (int) $disbursement->required_approvals) ? 'approved' : 'pending_approval');
        $this->db->table('tz_trust_disbursements')->where('id', $disbursement->id)->update(['status' => $status, 'updated_at' => now()]);
        return $this->snapshot('tz_trust_disbursements', $companyId, $publicId);
    }

    public function releaseDisbursement(string $publicId, array $data, int $companyId, int $actorId): array
    {
        $this->guard($companyId, $actorId);
        return $this->db->transaction(function () use ($publicId, $data, $companyId, $actorId): array {
            $disbursement = $this->locked('tz_trust_disbursements', $companyId, $publicId);
            $approved = $this->db->table('tz_trust_approvals')->where('company_id', $companyId)->where('disbursement_id', $disbursement->id)->where('decision', 'approved')->count();
            if ($disbursement->status !== 'approved' || ! TrustLedgerPolicy::mayReleaseDisbursement($approved, (int) $disbursement->required_approvals)) throw new InvalidArgumentException('Trust disbursement lacks required approvals.');
            if ((int) $disbursement->amount_minor > $this->matterBalance((int) $disbursement->matter_id, $companyId)) throw new InvalidArgumentException('Trust matter funds are no longer sufficient.');
            $this->appendLedger((int) $disbursement->trust_account_id, (int) $disbursement->matter_id, 'disbursement', 'trust_disbursement', $publicId,
                -abs((int) $disbursement->amount_minor), (string) $disbursement->currency, null, $data['description'] ?? $disbursement->purpose, $companyId, $actorId);
            $this->db->table('tz_trust_disbursements')->where('id', $disbursement->id)->update([
                'status' => 'released', 'released_at' => $data['released_at'] ?? now(), 'released_by_user_id' => $actorId, 'updated_at' => now(),
            ]);
            return $this->snapshot('tz_trust_disbursements', $companyId, $publicId);
        }, 3);
    }

    public function reverseLedgerEntry(string $publicId, array $data, int $companyId, int $actorId): array
    {
        $this->guard($companyId, $actorId);
        $entry = $this->record('tz_trust_ledger_entries', $companyId, $publicId);
        $existing = $this->db->table('tz_trust_ledger_entries')->where('company_id', $companyId)->where('reversal_of_entry_id', $entry->id)->first();
        if ($existing) return (array) $existing;
        if ($entry->matter_id !== null && (int) $entry->amount_minor > 0
            && $this->matterBalance((int) $entry->matter_id, $companyId) < (int) $entry->amount_minor) {
            throw new InvalidArgumentException('Trust reversal would overdraw the matter.');
        }
        return $this->appendLedger((int) $entry->trust_account_id, $entry->matter_id ? (int) $entry->matter_id : null,
            'reversal', 'trust_ledger_entry', $publicId, -((int) $entry->amount_minor), (string) $entry->currency,
            (int) $entry->id, $data['reason'] ?? 'Trust ledger correction', $companyId, $actorId);
    }

    public function trustSummary(int $companyId, array $filters = []): array
    {
        $this->assertCompany($companyId);
        return [
            'accounts' => $this->db->table('tz_trust_accounts')->where('company_id', $companyId)->where('active', true)->count(),
            'open_matters' => $this->db->table('tz_trust_matters')->where('company_id', $companyId)->where('status', 'open')->count(),
            'ledger_balance_minor' => (int) $this->db->table('tz_trust_ledger_entries')->where('company_id', $companyId)->sum('amount_minor'),
            'pending_disbursements' => $this->db->table('tz_trust_disbursements')->where('company_id', $companyId)->whereIn('status', ['pending_approval', 'approved'])->count(),
            'reconciliation_exceptions_minor' => (int) $this->db->table('tz_trust_reconciliations')->where('company_id', $companyId)->where('status', '!=', 'completed')->sum('difference_minor'),
            'currency' => (string) ($filters['currency'] ?? 'AUD'),
        ];
    }

    public function matterLedger(string $matterPublicId, int $companyId): array
    {
        $this->assertCompany($companyId);
        $matter = $this->record('tz_trust_matters', $companyId, $matterPublicId);
        $entries = $this->db->table('tz_trust_ledger_entries')->where('company_id', $companyId)->where('matter_id', $matter->id)->orderBy('posted_at')->get()->all();
        return ['matter' => (array) $matter, 'balance_minor' => $this->matterBalance((int) $matter->id, $companyId), 'entries' => array_map(static fn ($row) => (array) $row, $entries)];
    }

    private function appendLedger(int $accountId, ?int $matterId, string $entryType, string $sourceType, ?string $sourcePublicId, int $amount, string $currency, ?int $reversalId, ?string $description, int $companyId, int $actorId): array
    {
        $publicId = (string) Str::ulid();
        $this->db->table('tz_trust_ledger_entries')->insert([
            'public_id' => $publicId, 'company_id' => $companyId, 'trust_account_id' => $accountId, 'matter_id' => $matterId,
            'entry_type' => $entryType, 'source_type' => $sourceType, 'source_public_id' => $sourcePublicId,
            'amount_minor' => $amount, 'currency' => $currency, 'reversal_of_entry_id' => $reversalId,
            'posted_at' => now(), 'description' => $description, 'posted_by_user_id' => $actorId,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        return $this->snapshot('tz_trust_ledger_entries', $companyId, $publicId);
    }

    private function matterBalance(int $matterId, int $companyId): int { return (int) $this->db->table('tz_trust_ledger_entries')->where('company_id', $companyId)->where('matter_id', $matterId)->sum('amount_minor'); }
    private function guard(int $companyId, int $actorId): void { $this->assertCompany($companyId); if ($actorId !== $this->actorId()) throw new InvalidArgumentException('Actor mismatch.'); }
    private function optionalId(string $table, int $companyId, mixed $publicId): ?int { return is_string($publicId) && $publicId !== '' ? (int) $this->record($table, $companyId, $publicId)->id : null; }
    private function optionalPublicId(mixed $publicId): ?string { $value = is_string($publicId) ? trim($publicId) : ''; if ($value === '') return null; if (strlen($value) !== 26) throw new InvalidArgumentException('Agreement public ID must be a 26-character ULID.'); return $value; }
    private function record(string $table, int $companyId, string $publicId): object { $row = $this->db->table($table)->where('company_id', $companyId)->where('public_id', $publicId)->first(); if (! $row) throw new InvalidArgumentException('Trust record not found.'); return $row; }
    private function locked(string $table, int $companyId, string $publicId): object { $row = $this->db->table($table)->where('company_id', $companyId)->where('public_id', $publicId)->lockForUpdate()->first(); if (! $row) throw new InvalidArgumentException('Trust record not found.'); return $row; }
    private function snapshot(string $table, int $companyId, string $publicId): array { return (array) $this->record($table, $companyId, $publicId); }
    private function json(mixed $value): ?string { return $value === null ? null : json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES); }
}
