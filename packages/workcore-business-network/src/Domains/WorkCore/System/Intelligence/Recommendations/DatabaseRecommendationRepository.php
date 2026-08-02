<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Intelligence\Recommendations;

use Illuminate\Database\ConnectionInterface;

final class DatabaseRecommendationRepository implements RecommendationRepositoryContract
{
    public function __construct(private ConnectionInterface $db) {}

    public function save(IntelligenceRecommendation $recommendation, int $actorId, ?string $correlationId = null): IntelligenceRecommendation
    {
        $this->db->table('tz_ai_recommendations')->insert([
            'public_id' => $recommendation->publicId,
            'company_id' => $recommendation->companyId,
            'observation_public_id' => $recommendation->observationPublicId,
            'summary' => $recommendation->summary,
            'proposed_action' => $recommendation->proposedAction,
            'confidence' => $recommendation->confidence,
            'risk' => $recommendation->risk,
            'status' => $recommendation->status,
            'success_metrics_json' => json_encode($recommendation->successMetrics, JSON_THROW_ON_ERROR),
            'proposed_by_user_id' => $actorId,
            'correlation_id' => $correlationId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $recommendation;
    }

    public function listForCompany(int $companyId, ?string $status = null, int $limit = 50): array
    {
        $query = $this->db->table('tz_ai_recommendations')->where('company_id', $companyId);
        if ($status !== null && trim($status) !== '') {
            $query->where('status', trim($status));
        }

        return array_map(static fn (object $row): array => (array) $row, $query->orderByDesc('id')->limit(max(1, min(100, $limit)))->get()->all());
    }
}
