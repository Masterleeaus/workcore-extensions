<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Contracts\Records\Adapters;

use App\Domains\WorkCore\System\Contracts\Records\FeedbackAccessContract;

final class FeedbackAccessAdapter extends AbstractDatabaseRecordAccess implements FeedbackAccessContract
{
    protected function table(): string { return 'tz_feedback_items'; }
    protected function type(): string { return 'feedback_item'; }
    protected function columns(): array
    {
        return [
            'public_id','customer_id','contact_id','premises_id','work_order_id','appointment_id','worker_id','service_id',
            'kind','visibility','rating','nps_score','csat_score','sentiment','status','priority','severity',
            'moderation_status','publication_status','consent_status','requires_investigation','created_at','updated_at',
        ];
    }

    public function find(int $companyId, string $publicId): ?array
    {
        return $this->findByPublicId($publicId, $companyId)?->toArray();
    }
}
