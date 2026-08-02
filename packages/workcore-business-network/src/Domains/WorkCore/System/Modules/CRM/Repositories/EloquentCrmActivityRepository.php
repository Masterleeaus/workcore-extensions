<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\CRM\Repositories;

use App\Domains\WorkCore\System\Authorization\CompanyRecordAuthorizer;
use App\Domains\WorkCore\System\Authorization\WorkCoreAccessLevel;
use App\Domains\WorkCore\System\Contracts\TenantContextContract;
use App\Domains\WorkCore\System\Modules\CRM\Contracts\CrmActivityRepositoryContract;
use App\Domains\WorkCore\System\Modules\CRM\Data\CrmActivityData;
use App\Domains\WorkCore\System\Modules\CRM\Services\CrmActivityRecorder;
use App\Domains\WorkCore\System\Modules\CRM\Services\CrmSubjectReferenceValidator;
use App\Domains\WorkCore\System\Persistence\TenantScopedRepository;
use Illuminate\Database\ConnectionInterface;

final class EloquentCrmActivityRepository extends TenantScopedRepository implements CrmActivityRepositoryContract
{
    public function __construct(
        ConnectionInterface $db,
        TenantContextContract $tenant,
        private CrmActivityRecorder $recorder,
        private CrmSubjectReferenceValidator $subjects,
        private CompanyRecordAuthorizer $authorizer,
    ) {
        parent::__construct($db, $tenant);
    }

    public function create(CrmActivityData $data, int $companyId, int $actorId): array
    {
        $this->assertActor($companyId, $actorId);

        return $this->recorder->record(
            $companyId,
            $data->subjectType,
            $data->subjectPublicId,
            $data->type,
            $data->title,
            $data->body,
            $data->direction,
            $data->scheduledAt,
            $data->completedAt,
            $actorId,
            $data->metadata,
        );
    }

    public function forSubject(int $companyId, string $subjectType, string $subjectPublicId, WorkCoreAccessLevel $accessLevel, int $actorId, int $limit = 100): array
    {
        $this->assertActor($companyId, $actorId);
        $this->subjects->require($companyId, $subjectType, $subjectPublicId);
        $query = $this->db->table('tz_crm_activities')
            ->where('company_id', $companyId)
            ->where('subject_type', $subjectType)
            ->where('subject_public_id', $subjectPublicId);
        $this->authorizer->apply($query, $accessLevel, $actorId, 'actor_user_id', ['actor_user_id']);

        return $query->orderByDesc('created_at')->limit(max(1, min($limit, 250)))
            ->get(['public_id', 'subject_type', 'subject_public_id', 'type', 'title', 'body', 'direction', 'scheduled_at', 'completed_at', 'actor_user_id', 'metadata', 'created_at', 'updated_at'])
            ->map(static fn (object $row): array => (array) $row)->all();
    }

    private function assertActor(int $companyId, int $actorId): void
    {
        $this->assertCompany($companyId);
        if ($actorId !== $this->actorId()) {
            throw new \RuntimeException('Repository actor does not match the active WorkCore actor.');
        }
    }
}
