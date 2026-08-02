<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Intelligence\Observations;

use Illuminate\Database\ConnectionInterface;

final class DatabaseObservationRepository implements ObservationRepositoryContract
{
    public function __construct(private ConnectionInterface $db) {}

    public function save(IntelligenceObservation $observation, int $actorId, ?string $correlationId = null): IntelligenceObservation
    {
        $this->db->table('tz_ai_observations')->insert([
            'public_id' => $observation->publicId,
            'company_id' => $observation->companyId,
            'domain_key' => $observation->domain,
            'observation_type' => $observation->type,
            'summary' => $observation->summary,
            'confidence' => $observation->confidence,
            'evidence_json' => json_encode($observation->evidence, JSON_THROW_ON_ERROR),
            'data_gaps_json' => json_encode($observation->dataGaps, JSON_THROW_ON_ERROR),
            'status' => $observation->status,
            'observed_by_user_id' => $actorId,
            'correlation_id' => $correlationId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $observation;
    }

    public function listForCompany(int $companyId, ?string $domain = null, int $limit = 50): array
    {
        $query = $this->db->table('tz_ai_observations')->where('company_id', $companyId);
        if ($domain !== null && trim($domain) !== '') {
            $query->where('domain_key', trim($domain));
        }

        return array_map(static fn (object $row): array => (array) $row, $query->orderByDesc('id')->limit(max(1, min(100, $limit)))->get()->all());
    }
}
