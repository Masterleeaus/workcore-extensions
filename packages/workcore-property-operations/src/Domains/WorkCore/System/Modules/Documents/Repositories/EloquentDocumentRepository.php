<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Documents\Repositories;

use App\Domains\WorkCore\System\Contracts\TenantContextContract;
use App\Domains\WorkCore\System\Modules\Documents\Contracts\DocumentRepositoryContract;
use App\Domains\WorkCore\System\Modules\Documents\Services\CanonicalPayloadHasher;
use App\Domains\WorkCore\System\Modules\Documents\Services\EvidenceSignoffPolicy;
use App\Domains\WorkCore\System\Modules\Documents\Services\OperationalSubjectResolver;
use App\Domains\WorkCore\System\Persistence\TenantScopedRepository;
use App\Domains\WorkCore\System\Validation\CompanyScopedReferenceValidator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class EloquentDocumentRepository extends TenantScopedRepository implements DocumentRepositoryContract
{
    public function __construct(
        ConnectionInterface $db,
        TenantContextContract $tenant,
        private OperationalSubjectResolver $subjects,
        private CompanyScopedReferenceValidator $references,
        private CanonicalPayloadHasher $hasher,
        private EvidenceSignoffPolicy $signoffPolicy,
    ) {
        parent::__construct($db, $tenant);
    }

    public function searchDocuments(int $companyId, array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        $this->assertCompany($companyId);
        $query = $this->db->table('tz_documents as d')
            ->leftJoin('users as owner', 'owner.id', '=', 'd.owner_user_id')
            ->where('d.company_id', $companyId)
            ->whereNull('d.deleted_at');

        if (($filters['status'] ?? null) !== null) {
            $query->where('d.status', (string) $filters['status']);
        }
        if (($filters['document_type'] ?? null) !== null) {
            $query->where('d.document_type', (string) $filters['document_type']);
        }
        if (($filters['subject_type'] ?? null) !== null && ($filters['subject_public_id'] ?? null) !== null) {
            $query->join('tz_document_links as dl', 'dl.document_id', '=', 'd.id')
                ->where('dl.company_id', $companyId)
                ->where('dl.subject_type', (string) $filters['subject_type'])
                ->where('dl.subject_public_id', (string) $filters['subject_public_id']);
        }
        if (($filters['query'] ?? null) !== null) {
            $term = '%' . trim((string) $filters['query']) . '%';
            $query->where(static fn ($q) => $q->where('d.title', 'like', $term)->orWhere('d.document_number', 'like', $term));
        }

        return $query->select([
            'd.public_id', 'd.document_number', 'd.document_type', 'd.title', 'd.description', 'd.status',
            'd.visibility', 'd.current_version_number', 'd.retention_class', 'd.retention_until', 'd.legal_hold',
            'd.created_at', 'd.updated_at', 'owner.name as owner_name',
        ])->orderByDesc('d.updated_at')->paginate(max(1, min(100, $perPage)));
    }

    public function searchEvidence(int $companyId, array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        $this->assertCompany($companyId);
        $query = $this->db->table('tz_evidence_items as e')
            ->leftJoin('tz_documents as d', 'd.id', '=', 'e.document_id')
            ->where('e.company_id', $companyId)
            ->whereNull('e.deleted_at');

        foreach (['status', 'evidence_type', 'phase', 'subject_type', 'subject_public_id', 'source_type', 'source_public_id'] as $field) {
            if (($filters[$field] ?? null) !== null) {
                $query->where("e.{$field}", (string) $filters[$field]);
            }
        }

        return $query->select([
            'e.public_id', 'e.evidence_type', 'e.phase', 'e.status', 'e.title', 'e.description',
            'e.subject_type', 'e.subject_public_id', 'e.source_type', 'e.source_public_id',
            'e.checksum_sha256', 'e.captured_at', 'e.created_at', 'd.public_id as document_public_id', 'd.title as document_title',
        ])->orderByDesc('e.captured_at')->orderByDesc('e.created_at')->paginate(max(1, min(100, $perPage)));
    }

    public function createDocument(array $data, int $companyId, int $actorId): array
    {
        $this->guard($data, $companyId, $actorId);
        $title = trim((string) ($data['title'] ?? ''));
        $type = trim((string) ($data['document_type'] ?? ''));
        if ($title === '' || $type === '') {
            throw new InvalidArgumentException('Document title and document type are required.');
        }
        if (($data['owner_user_id'] ?? null) !== null) {
            $this->references->requireActiveMember($companyId, (int) $data['owner_user_id'], 'owner_user_id');
        }

        return $this->db->transaction(function () use ($data, $companyId, $actorId, $title, $type): array {
            $publicId = (string) Str::ulid();
            $documentId = $this->db->table('tz_documents')->insertGetId([
                'public_id' => $publicId,
                'company_id' => $companyId,
                'document_number' => $data['document_number'] ?? null,
                'document_type' => $type,
                'title' => $title,
                'description' => $data['description'] ?? null,
                'status' => $data['status'] ?? 'draft',
                'visibility' => $data['visibility'] ?? 'company',
                'current_version_number' => 0,
                'retention_class' => $data['retention_class'] ?? null,
                'retention_until' => $data['retention_until'] ?? null,
                'legal_hold' => (bool) ($data['legal_hold'] ?? false),
                'owner_user_id' => $data['owner_user_id'] ?? null,
                'created_by_user_id' => $actorId,
                'updated_by_user_id' => $actorId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if (($data['storage_path'] ?? null) !== null) {
                $this->createVersion($documentId, $publicId, $data, $companyId, $actorId);
            }

            return (array) $this->db->table('tz_documents')->where('id', $documentId)->first();
        }, 3);
    }

    public function addVersion(string $documentPublicId, array $data, int $companyId, int $actorId): array
    {
        $this->guard($data, $companyId, $actorId);
        return $this->db->transaction(function () use ($documentPublicId, $data, $companyId, $actorId): array {
            $document = $this->document($documentPublicId, $companyId, true);
            return $this->createVersion((int) $document->id, (string) $document->public_id, $data, $companyId, $actorId);
        }, 3);
    }

    public function linkDocument(string $documentPublicId, array $data, int $companyId, int $actorId): array
    {
        $this->guard($data, $companyId, $actorId);
        $document = $this->document($documentPublicId, $companyId);
        $subjectType = (string) ($data['subject_type'] ?? '');
        $subjectPublicId = (string) ($data['subject_public_id'] ?? '');
        $this->subjects->resolve($companyId, $subjectType, $subjectPublicId);
        $publicId = (string) Str::ulid();
        $this->db->table('tz_document_links')->insertOrIgnore([
            'public_id' => $publicId,
            'company_id' => $companyId,
            'document_id' => $document->id,
            'subject_type' => strtolower($subjectType),
            'subject_public_id' => $subjectPublicId,
            'relation_type' => $data['relation_type'] ?? 'attachment',
            'created_by_user_id' => $actorId,
            'created_at' => now(),
        ]);

        return (array) $this->db->table('tz_document_links')
            ->where('company_id', $companyId)->where('document_id', $document->id)
            ->where('subject_type', strtolower($subjectType))->where('subject_public_id', $subjectPublicId)
            ->where('relation_type', $data['relation_type'] ?? 'attachment')->first();
    }

    public function addComment(string $documentPublicId, array $data, int $companyId, int $actorId): array
    {
        $this->guard($data, $companyId, $actorId);
        $document = $this->document($documentPublicId, $companyId);
        $body = trim((string) ($data['body'] ?? ''));
        if ($body === '') {
            throw new InvalidArgumentException('Document comment body is required.');
        }
        $parentId = null;
        if (($data['parent_comment_public_id'] ?? null) !== null) {
            $parentId = $this->db->table('tz_document_comments')
                ->where('company_id', $companyId)->where('document_id', $document->id)
                ->where('public_id', (string) $data['parent_comment_public_id'])->value('id');
            if (! $parentId) {
                throw new InvalidArgumentException('Parent comment does not belong to the document.');
            }
        }
        $publicId = (string) Str::ulid();
        $this->db->table('tz_document_comments')->insert([
            'public_id' => $publicId, 'company_id' => $companyId, 'document_id' => $document->id,
            'parent_comment_id' => $parentId, 'body' => $body, 'status' => 'active', 'author_user_id' => $actorId,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        return (array) $this->db->table('tz_document_comments')->where('public_id', $publicId)->first();
    }

    public function requestApproval(string $documentPublicId, array $data, int $companyId, int $actorId): array
    {
        $this->guard($data, $companyId, $actorId);
        $document = $this->document($documentPublicId, $companyId);
        $approver = (int) ($data['approver_user_id'] ?? 0);
        $this->references->requireActiveMember($companyId, $approver, 'approver_user_id');
        $versionId = null;
        if (($data['document_version_public_id'] ?? null) !== null) {
            $version = $this->version((string) $data['document_version_public_id'], $companyId);
            if ((int) $version->document_id !== (int) $document->id) {
                throw new InvalidArgumentException('Document version does not belong to the selected document.');
            }
            $versionId = $version->id;
        }
        $publicId = (string) Str::ulid();
        $this->db->table('tz_document_approvals')->insert([
            'public_id' => $publicId, 'company_id' => $companyId, 'document_id' => $document->id,
            'document_version_id' => $versionId, 'approval_type' => $data['approval_type'] ?? 'document',
            'status' => 'pending', 'purpose' => $data['purpose'] ?? null, 'requested_by_user_id' => $actorId,
            'approver_user_id' => $approver, 'requested_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        return (array) $this->db->table('tz_document_approvals')->where('public_id', $publicId)->first();
    }

    public function decideApproval(string $approvalPublicId, array $data, int $companyId, int $actorId): array
    {
        $this->guard($data, $companyId, $actorId);
        $decision = strtolower((string) ($data['decision'] ?? ''));
        if (! in_array($decision, ['approved', 'rejected'], true)) {
            throw new InvalidArgumentException('Approval decision must be approved or rejected.');
        }

        return $this->db->transaction(function () use ($approvalPublicId, $data, $companyId, $actorId, $decision): array {
            $approval = $this->db->table('tz_document_approvals')->where('company_id', $companyId)
                ->where('public_id', $approvalPublicId)->lockForUpdate()->first();
            if (! $approval || (int) $approval->approver_user_id !== $actorId || $approval->status !== 'pending') {
                throw new InvalidArgumentException('Pending approval is not assigned to the active actor.');
            }
            $this->db->table('tz_document_approvals')->where('id', $approval->id)->update([
                'status' => $decision, 'decision_note' => $data['decision_note'] ?? null,
                'decided_at' => now(), 'updated_at' => now(),
            ]);
            if ($decision === 'approved') {
                $this->db->table('tz_documents')->where('company_id', $companyId)->where('id', $approval->document_id)
                    ->update(['status' => 'active', 'updated_by_user_id' => $actorId, 'updated_at' => now()]);
            }
            return (array) $this->db->table('tz_document_approvals')->where('id', $approval->id)->first();
        }, 3);
    }

    public function registerEvidence(array $data, int $companyId, int $actorId): array
    {
        $this->guard($data, $companyId, $actorId);
        $subjectType = (string) ($data['subject_type'] ?? '');
        $subjectPublicId = (string) ($data['subject_public_id'] ?? '');
        $this->subjects->resolve($companyId, $subjectType, $subjectPublicId);
        if (($data['source_type'] ?? null) !== null || ($data['source_public_id'] ?? null) !== null) {
            $this->subjects->resolve($companyId, (string) ($data['source_type'] ?? ''), (string) ($data['source_public_id'] ?? ''));
        }
        $checksum = $this->checksum($data['checksum_sha256'] ?? null);
        $document = ($data['document_public_id'] ?? null) ? $this->document((string) $data['document_public_id'], $companyId) : null;
        $version = ($data['document_version_public_id'] ?? null) ? $this->version((string) $data['document_version_public_id'], $companyId) : null;
        if ($version && $document && (int) $version->document_id !== (int) $document->id) {
            throw new InvalidArgumentException('Evidence document version does not belong to its document.');
        }

        return $this->db->transaction(function () use ($data, $companyId, $actorId, $subjectType, $subjectPublicId, $checksum, $document, $version): array {
            $publicId = (string) Str::ulid();
            $id = $this->db->table('tz_evidence_items')->insertGetId([
                'public_id' => $publicId, 'company_id' => $companyId, 'document_id' => $document?->id,
                'document_version_id' => $version?->id, 'source_type' => $data['source_type'] ?? null,
                'source_public_id' => $data['source_public_id'] ?? null, 'subject_type' => strtolower($subjectType),
                'subject_public_id' => $subjectPublicId, 'evidence_type' => $data['evidence_type'] ?? 'document',
                'phase' => $data['phase'] ?? 'unspecified', 'status' => 'active', 'title' => $data['title'] ?? null,
                'description' => $data['description'] ?? null, 'storage_disk' => $data['storage_disk'] ?? null,
                'storage_path' => $data['storage_path'] ?? null, 'original_name' => $data['original_name'] ?? null,
                'mime_type' => $data['mime_type'] ?? null, 'size_bytes' => $data['size_bytes'] ?? null,
                'checksum_sha256' => $checksum, 'captured_at' => $data['captured_at'] ?? now(),
                'captured_by_user_id' => $data['captured_by_user_id'] ?? $actorId,
                'metadata' => $this->json($data['metadata'] ?? null), 'created_by_user_id' => $actorId,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $record = (array) $this->db->table('tz_evidence_items')->where('id', $id)->first();
            $this->appendChainEvent($id, $companyId, $actorId, 'registered', $record);
            return $record;
        }, 3);
    }

    public function signoffEvidence(string $evidencePublicId, array $data, int $companyId, int $actorId): array
    {
        $this->guard($data, $companyId, $actorId);
        $outcome = strtolower((string) ($data['outcome'] ?? ''));
        if (! in_array($outcome, ['accepted', 'rejected'], true)) {
            throw new InvalidArgumentException('Evidence sign-off outcome must be accepted or rejected.');
        }

        return $this->db->transaction(function () use ($evidencePublicId, $data, $companyId, $actorId, $outcome): array {
            $evidence = $this->db->table('tz_evidence_items')->where('company_id', $companyId)
                ->where('public_id', $evidencePublicId)->whereNull('deleted_at')->lockForUpdate()->first();
            if (! $evidence || ! $this->signoffPolicy->isSignable((array) $evidence)) {
                throw new InvalidArgumentException('Evidence is not eligible for sign-off.');
            }
            $publicId = (string) Str::ulid();
            $this->db->table('tz_evidence_signoffs')->insert([
                'public_id' => $publicId, 'company_id' => $companyId, 'evidence_item_id' => $evidence->id,
                'signoff_type' => $data['signoff_type'] ?? 'verification', 'outcome' => $outcome,
                'signer_role' => $data['signer_role'] ?? null, 'statement' => $data['statement'] ?? null,
                'signed_checksum_sha256' => $evidence->checksum_sha256, 'signer_user_id' => $actorId,
                'signed_at' => now(), 'signature_metadata' => $this->json($data['signature_metadata'] ?? null), 'created_at' => now(),
            ]);
            $signoff = (array) $this->db->table('tz_evidence_signoffs')->where('public_id', $publicId)->first();
            $this->appendChainEvent((int) $evidence->id, $companyId, $actorId, 'signed_off', $signoff);
            if ($outcome === 'accepted') {
                $this->db->table('tz_evidence_items')->where('id', $evidence->id)->update(['status' => 'verified', 'updated_at' => now()]);
            }
            return $signoff;
        }, 3);
    }

    private function createVersion(int $documentId, string $documentPublicId, array $data, int $companyId, int $actorId): array
    {
        $storagePath = trim((string) ($data['storage_path'] ?? ''));
        $originalName = trim((string) ($data['original_name'] ?? ''));
        if ($storagePath === '' || $originalName === '') {
            throw new InvalidArgumentException('Private storage path and original file name are required.');
        }
        $checksum = $this->checksum($data['checksum_sha256'] ?? null);
        $current = (int) $this->db->table('tz_documents')->where('company_id', $companyId)->where('id', $documentId)
            ->lockForUpdate()->value('current_version_number');
        $versionNumber = $current + 1;
        $publicId = (string) Str::ulid();
        $this->db->table('tz_document_versions')->insert([
            'public_id' => $publicId, 'company_id' => $companyId, 'document_id' => $documentId,
            'version_number' => $versionNumber, 'storage_disk' => $data['storage_disk'] ?? 'private',
            'storage_path' => $storagePath, 'original_name' => $originalName, 'mime_type' => $data['mime_type'] ?? null,
            'size_bytes' => $data['size_bytes'] ?? null, 'checksum_sha256' => $checksum,
            'change_summary' => $data['change_summary'] ?? null, 'metadata' => $this->json($data['metadata'] ?? null),
            'created_by_user_id' => $actorId, 'created_at' => now(),
        ]);
        $this->db->table('tz_documents')->where('company_id', $companyId)->where('id', $documentId)->update([
            'current_version_number' => $versionNumber, 'status' => (bool) ($data['activate'] ?? true) ? 'active' : 'draft',
            'updated_by_user_id' => $actorId, 'updated_at' => now(),
        ]);
        $record = (array) $this->db->table('tz_document_versions')->where('public_id', $publicId)->first();
        $record['document_public_id'] = $documentPublicId;
        return $record;
    }

    private function appendChainEvent(int $evidenceId, int $companyId, int $actorId, string $eventType, array $payload): void
    {
        $previous = $this->db->table('tz_evidence_chain_events')->where('company_id', $companyId)
            ->where('evidence_item_id', $evidenceId)->orderByDesc('id')->value('event_hash');
        $payloadHash = $this->hasher->hash($payload);
        $eventHash = $this->hasher->hash(['previous' => $previous, 'payload_hash' => $payloadHash, 'event_type' => $eventType]);
        $this->db->table('tz_evidence_chain_events')->insert([
            'public_id' => (string) Str::ulid(), 'company_id' => $companyId, 'evidence_item_id' => $evidenceId,
            'event_type' => $eventType, 'previous_event_hash' => $previous, 'payload_hash' => $payloadHash,
            'event_hash' => $eventHash, 'actor_user_id' => $actorId, 'occurred_at' => now(), 'created_at' => now(),
        ]);
    }

    private function document(string $publicId, int $companyId, bool $lock = false): object
    {
        $query = $this->db->table('tz_documents')->where('company_id', $companyId)->where('public_id', $publicId)->whereNull('deleted_at');
        if ($lock) {
            $query->lockForUpdate();
        }
        return $query->first() ?? throw new InvalidArgumentException('Document does not belong to the active company.');
    }

    private function version(string $publicId, int $companyId): object
    {
        return $this->db->table('tz_document_versions')->where('company_id', $companyId)->where('public_id', $publicId)->first()
            ?? throw new InvalidArgumentException('Document version does not belong to the active company.');
    }

    private function checksum(mixed $checksum): string
    {
        $checksum = strtolower(trim((string) $checksum));
        if (preg_match('/^[a-f0-9]{64}$/', $checksum) !== 1) {
            throw new InvalidArgumentException('A SHA-256 hexadecimal checksum is required.');
        }
        return $checksum;
    }

    private function guard(array $data, int $companyId, int $actorId): void
    {
        $this->assertCompany($companyId);
        if ($actorId < 1 || $actorId !== $this->actorId()) {
            throw new InvalidArgumentException('Document action actor does not match the active user.');
        }
        if (array_key_exists('company_id', $data) || array_key_exists('companyId', $data)) {
            throw new InvalidArgumentException('Company authority cannot be supplied in a document action payload.');
        }
    }

    private function json(mixed $value): ?string
    {
        return $value === null ? null : json_encode($value, JSON_THROW_ON_ERROR);
    }
}
