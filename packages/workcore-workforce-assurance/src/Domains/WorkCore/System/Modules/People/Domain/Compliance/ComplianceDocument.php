<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\People\Domain\Compliance;

use DateTimeImmutable;
use DomainException;
use InvalidArgumentException;

final class ComplianceDocument
{
    private ComplianceDocumentState $state = ComplianceDocumentState::Draft;
    private ?string $verifiedBy = null;

    public function __construct(
        public readonly string $id,
        public readonly string $companyId,
        public readonly string $workerId,
        public readonly string $typeCode,
        private readonly string $evidenceHash,
        public readonly ?DateTimeImmutable $expiresOn = null,
    ) {
        if (trim($id) === '' || trim($companyId) === '' || trim($workerId) === '' || trim($typeCode) === '' || trim($evidenceHash) === '') {
            throw new InvalidArgumentException('Compliance identity, type and evidence reference are required.');
        }
    }

    public function state(): ComplianceDocumentState { return $this->state; }
    public function evidenceHash(): string { return $this->evidenceHash; }
    public function submit(): void
    {
        if ($this->state !== ComplianceDocumentState::Draft) throw new DomainException('Only draft documents can be submitted.');
        $this->state = ComplianceDocumentState::Submitted;
    }
    public function verify(string $actorId, DateTimeImmutable $verifiedAt): void
    {
        if ($this->state !== ComplianceDocumentState::Submitted || trim($actorId) === '') throw new DomainException('Only submitted documents can be verified by a named actor.');
        if ($this->expiresOn && $this->expiresOn < $verifiedAt) throw new DomainException('Expired evidence cannot be verified.');
        $this->verifiedBy = $actorId;
        $this->state = ComplianceDocumentState::Verified;
    }
    public function evaluateExpiry(DateTimeImmutable $asAt): void
    {
        if ($this->expiresOn && $asAt > $this->expiresOn && $this->state === ComplianceDocumentState::Verified) $this->state = ComplianceDocumentState::Expired;
    }
    public function revoke(): void
    {
        if (! in_array($this->state, [ComplianceDocumentState::Verified, ComplianceDocumentState::Expired], true)) throw new DomainException('Only verified or expired documents can be revoked.');
        $this->state = ComplianceDocumentState::Revoked;
    }
}
