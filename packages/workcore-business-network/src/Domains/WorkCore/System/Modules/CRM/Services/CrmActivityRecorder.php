<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\CRM\Services;

use App\Domains\WorkCore\System\Contracts\TenantContextContract;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

final class CrmActivityRecorder
{
    public function __construct(
        private ConnectionInterface $db,
        private TenantContextContract $tenant,
        private CrmSubjectReferenceValidator $subjects,
    ) {}

    /** @param array<string,mixed> $metadata @return array<string,mixed> */
    public function record(
        int $companyId,
        string $subjectType,
        string $subjectPublicId,
        string $type,
        ?string $title,
        ?string $body,
        ?string $direction,
        ?string $scheduledAt,
        ?string $completedAt,
        ?int $actorId,
        array $metadata = [],
    ): array {
        if ($this->tenant->companyId() !== $companyId) {
            throw new RuntimeException('CRM activity company does not match the active WorkCore company.');
        }
        if (! in_array($type, ['note', 'call', 'email', 'meeting', 'task', 'system'], true)) {
            throw ValidationException::withMessages(['type' => ['The selected CRM activity type is not supported.']]);
        }
        if ($direction !== null && ! in_array($direction, ['inbound', 'outbound', 'internal'], true)) {
            throw ValidationException::withMessages(['direction' => ['The selected CRM activity direction is not supported.']]);
        }
        if ($type !== 'system' && trim((string) $title) === '' && trim((string) $body) === '') {
            throw ValidationException::withMessages(['body' => ['A CRM activity requires a title or body.']]);
        }
        $this->subjects->require($companyId, $subjectType, $subjectPublicId);
        $publicId = (string) Str::ulid();
        $this->db->table('tz_crm_activities')->insert([
            'public_id' => $publicId,
            'company_id' => $companyId,
            'subject_type' => $subjectType,
            'subject_public_id' => $subjectPublicId,
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'direction' => $direction,
            'scheduled_at' => $scheduledAt,
            'completed_at' => $completedAt,
            'actor_user_id' => $actorId,
            'metadata' => $metadata === [] ? null : json_encode($metadata, JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (array) $this->db->table('tz_crm_activities')->where('public_id', $publicId)->first();
    }
}
