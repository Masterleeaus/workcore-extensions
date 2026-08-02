<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Intelligence\Knowledge;

use App\Domains\WorkCore\System\Intelligence\Knowledge\Retention\KnowledgeRetentionPolicy;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class DatabaseKnowledgeIndex implements KnowledgeIndexContract, KnowledgeIndexStatusContract
{
    public function __construct(
        private ConnectionInterface $db,
        private CharacterChunker $chunker,
        private KnowledgeFreshnessPolicy $freshness,
        private KnowledgeRetentionPolicy $retention,
        private LexicalRelevanceScorer $fallbackScorer,
        private int $candidateMultiplier = 5,
    ) {
        if ($candidateMultiplier < 1 || $candidateMultiplier > 50) {
            throw new InvalidArgumentException('Knowledge candidate multiplier must be between 1 and 50.');
        }
    }

    /** @return array<string,mixed> */
    public function index(KnowledgeDocument $document): array
    {
        return $this->db->transaction(function () use ($document): array {
            $existing = $this->db->table('tz_ai_knowledge_documents')
                ->where('company_id', $document->companyId)
                ->where('public_id', $document->documentId)
                ->lockForUpdate()
                ->first();

            if ($existing !== null && $existing->content_hash === $document->contentHash() && $existing->deleted_at === null) {
                return [
                    'indexed' => false,
                    'unchanged' => true,
                    'document_id' => $document->documentId,
                    'content_hash' => $document->contentHash(),
                    'chunk_count' => (int) $this->db->table('tz_ai_knowledge_chunks')
                        ->where('company_id', $document->companyId)
                        ->where('document_public_id', $document->documentId)
                        ->count(),
                ];
            }

            $now = now();
            $documentRow = [
                'company_id' => $document->companyId,
                'public_id' => $document->documentId,
                'source_type' => $document->sourceType,
                'source_public_id' => $document->sourcePublicId,
                'title' => $document->effectiveTitle(),
                'visibility' => $document->visibility,
                'required_permission' => $document->requiredPermission,
                'owner_user_id' => $document->ownerUserId,
                'content' => $document->content,
                'content_hash' => $document->contentHash(),
                'content_length' => strlen($document->content),
                'metadata_json' => json_encode($document->metadata, JSON_THROW_ON_ERROR),
                'source_updated_at' => $document->sourceUpdatedAt,
                'indexed_at' => $now,
                'expires_at' => $document->expiresAt,
                'deleted_at' => null,
                'created_by_user_id' => $document->createdByUserId,
                'updated_at' => $now,
            ];

            if ($existing === null) {
                $documentRow['created_at'] = $now;
                $this->db->table('tz_ai_knowledge_documents')->insert($documentRow);
            } else {
                $this->db->table('tz_ai_knowledge_documents')
                    ->where('company_id', $document->companyId)
                    ->where('public_id', $document->documentId)
                    ->update($documentRow);
            }

            $this->db->table('tz_ai_knowledge_chunks')
                ->where('company_id', $document->companyId)
                ->where('document_public_id', $document->documentId)
                ->delete();

            $chunks = $this->chunker->chunk($document->content, $document->metadata);
            foreach ($chunks as $chunk) {
                $this->db->table('tz_ai_knowledge_chunks')->insert([
                    'public_id' => (string) Str::uuid(),
                    'company_id' => $document->companyId,
                    'document_public_id' => $document->documentId,
                    'sequence' => $chunk->sequence,
                    'visibility' => $document->visibility,
                    'required_permission' => $document->requiredPermission,
                    'owner_user_id' => $document->ownerUserId,
                    'content' => $chunk->content,
                    'content_hash' => hash('sha256', $chunk->content),
                    'token_estimate' => $this->estimateTokens($chunk->content),
                    'start_offset' => $chunk->startOffset,
                    'end_offset' => $chunk->endOffset,
                    'metadata_json' => json_encode($chunk->metadata, JSON_THROW_ON_ERROR),
                    'source_updated_at' => $document->sourceUpdatedAt,
                    'indexed_at' => $now,
                    'expires_at' => $document->expiresAt,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            return [
                'indexed' => true,
                'unchanged' => false,
                'document_id' => $document->documentId,
                'content_hash' => $document->contentHash(),
                'chunk_count' => count($chunks),
            ];
        }, 3);
    }

    public function remove(int $companyId, string $documentId): void
    {
        if ($companyId < 1 || trim($documentId) === '') {
            throw new InvalidArgumentException('Company and knowledge document are required.');
        }

        $this->db->transaction(function () use ($companyId, $documentId): void {
            $row = $this->db->table('tz_ai_knowledge_documents')
                ->where('company_id', $companyId)
                ->where('public_id', $documentId)
                ->lockForUpdate()
                ->first();
            if ($row === null) {
                return;
            }

            $now = now();
            $this->db->table('tz_ai_knowledge_documents')
                ->where('company_id', $companyId)
                ->where('public_id', $documentId)
                ->update(['deleted_at' => $now, 'updated_at' => $now]);
            $this->db->table('tz_ai_knowledge_chunks')
                ->where('company_id', $companyId)
                ->where('document_public_id', $documentId)
                ->delete();
        }, 3);
    }

    public function search(KnowledgeQuery $query): KnowledgeRetrievalResult
    {
        $startedAt = hrtime(true);
        $candidateLimit = min(500, $query->limit * $this->candidateMultiplier);
        $driver = strtolower((string) $this->db->getDriverName());

        $builder = $this->baseSearchQuery($query);
        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            $builder->selectRaw('MATCH(k.content) AGAINST (? IN NATURAL LANGUAGE MODE) AS lexical_score', [$query->query]);
            $builder->whereRaw('MATCH(k.content) AGAINST (? IN NATURAL LANGUAGE MODE)', [$query->query]);
            $strategy = 'mysql_fulltext';
        } else {
            $builder->addSelect($this->db->raw('0 AS lexical_score'));
            $builder->where('k.content', 'like', '%' . $this->escapeLike($query->query) . '%');
            $strategy = 'database_lexical_fallback';
        }

        $rows = $builder->limit($candidateLimit)->get()->all();
        $totalCandidates = count($rows);
        $matches = [];
        $citations = [];
        $excludeStale = (bool) ($query->filters['exclude_stale'] ?? false);
        $now = new \DateTimeImmutable();

        foreach ($rows as $row) {
            $stale = $this->freshness->isStale($row->source_updated_at ?? null, $row->indexed_at ?? null, $now);
            if ($excludeStale && $stale) {
                continue;
            }

            $rawScore = (float) ($row->lexical_score ?? 0.0);
            $score = $rawScore > 0.0 ? $rawScore / (1.0 + $rawScore) : $this->fallbackScorer->score($query->query, (string) $row->content);
            if ($score <= 0.0) {
                continue;
            }

            $score = min(1.0, round($score, 6));
            $citation = new KnowledgeCitation(
                documentId: (string) $row->document_public_id,
                chunkId: (string) $row->chunk_public_id,
                title: (string) $row->title,
                sourceType: (string) $row->source_type,
                sourcePublicId: $row->source_public_id !== null ? (string) $row->source_public_id : null,
                sourceUpdatedAt: $row->source_updated_at !== null ? (string) $row->source_updated_at : null,
                indexedAt: $row->indexed_at !== null ? (string) $row->indexed_at : null,
                stale: $stale,
                excerpt: (string) $row->content,
                score: $score,
            );
            $citations[] = $citation;
            $matches[] = [
                'document_id' => (string) $row->document_public_id,
                'chunk_id' => (string) $row->chunk_public_id,
                'sequence' => (int) $row->sequence,
                'content' => (string) $row->content,
                'score' => $score,
                'stale' => $stale,
                'citation' => $citation->toArray(),
                'metadata' => $this->decodeMetadata($row->metadata_json ?? null),
            ];
        }

        usort($matches, static fn (array $left, array $right): int => $right['score'] <=> $left['score']);
        $matches = array_slice($matches, 0, $query->limit);
        $allowedChunkIds = array_fill_keys(array_column($matches, 'chunk_id'), true);
        $citations = array_values(array_filter($citations, static fn (KnowledgeCitation $citation): bool => isset($allowedChunkIds[$citation->chunkId])));

        return new KnowledgeRetrievalResult(
            matches: $matches,
            retrievalTimeMs: (int) round((hrtime(true) - $startedAt) / 1_000_000),
            strategy: $strategy,
            citations: $citations,
            totalCandidates: $totalCandidates,
        );
    }

    public function status(int $companyId, string $documentId): ?array
    {
        $row = $this->db->table('tz_ai_knowledge_documents')
            ->where('company_id', $companyId)
            ->where('public_id', $documentId)
            ->first();
        if ($row === null) {
            return null;
        }

        return [
            'document_id' => (string) $row->public_id,
            'source_type' => (string) $row->source_type,
            'source_public_id' => $row->source_public_id !== null ? (string) $row->source_public_id : null,
            'title' => (string) $row->title,
            'visibility' => (string) $row->visibility,
            'required_permission' => $row->required_permission !== null ? (string) $row->required_permission : null,
            'owner_user_id' => $row->owner_user_id !== null ? (int) $row->owner_user_id : null,
            'content_hash' => (string) $row->content_hash,
            'content_length' => (int) $row->content_length,
            'source_updated_at' => $row->source_updated_at !== null ? (string) $row->source_updated_at : null,
            'indexed_at' => $row->indexed_at !== null ? (string) $row->indexed_at : null,
            'expires_at' => $row->expires_at !== null ? (string) $row->expires_at : null,
            'deleted_at' => $row->deleted_at !== null ? (string) $row->deleted_at : null,
            'chunk_count' => (int) $this->db->table('tz_ai_knowledge_chunks')
                ->where('company_id', $companyId)
                ->where('document_public_id', $documentId)
                ->count(),
        ];
    }

    public function purgeExpired(int $companyId, ?string $before = null): int
    {
        if ($companyId < 1) {
            throw new InvalidArgumentException('Company is required to purge knowledge.');
        }

        $expiryCutoff = $before ?? now()->toDateTimeString();
        $deletedCutoff = $before ?? now()->subDays($this->retention->deletedGraceDays)->toDateTimeString();
        $documents = $this->db->table('tz_ai_knowledge_documents')
            ->where('company_id', $companyId)
            ->where(function (Builder $query) use ($expiryCutoff, $deletedCutoff): void {
                $query->where(function (Builder $expired) use ($expiryCutoff): void {
                    $expired->whereNotNull('expires_at')->where('expires_at', '<=', $expiryCutoff);
                })->orWhere(function (Builder $deleted) use ($deletedCutoff): void {
                    $deleted->whereNotNull('deleted_at')->where('deleted_at', '<=', $deletedCutoff);
                });
            })
            ->pluck('public_id')
            ->all();

        if ($documents === []) {
            return 0;
        }

        return $this->db->transaction(function () use ($companyId, $documents): int {
            $this->db->table('tz_ai_knowledge_chunks')
                ->where('company_id', $companyId)
                ->whereIn('document_public_id', $documents)
                ->delete();

            return $this->db->table('tz_ai_knowledge_documents')
                ->where('company_id', $companyId)
                ->whereIn('public_id', $documents)
                ->delete();
        }, 3);
    }

    private function baseSearchQuery(KnowledgeQuery $query): Builder
    {
        $builder = $this->db->table('tz_ai_knowledge_chunks as k')
            ->join('tz_ai_knowledge_documents as d', function ($join): void {
                $join->on('d.company_id', '=', 'k.company_id')
                    ->on('d.public_id', '=', 'k.document_public_id');
            })
            ->select([
                'k.public_id as chunk_public_id',
                'k.document_public_id',
                'k.sequence',
                'k.content',
                'k.metadata_json',
                'k.source_updated_at',
                'k.indexed_at',
                'd.title',
                'd.source_type',
                'd.source_public_id',
            ])
            ->where('k.company_id', $query->companyId)
            ->where('d.company_id', $query->companyId)
            ->whereNull('d.deleted_at')
            ->whereIn('k.visibility', $query->allowedVisibilities)
            ->where(function (Builder $access) use ($query): void {
                $access->whereNull('k.owner_user_id')
                    ->orWhere(function (Builder $owned) use ($query): void {
                        $owned->where('k.owner_user_id', $query->actorId);
                    });
            })
            ->where(function (Builder $permission) use ($query): void {
                $permission->whereNull('k.required_permission');
                if ($query->allowedPermissions !== []) {
                    $permission->orWhereIn('k.required_permission', $query->allowedPermissions);
                }
            })
            ->where(function (Builder $expiry): void {
                $expiry->whereNull('k.expires_at')->orWhere('k.expires_at', '>', now());
            });

        if (isset($query->filters['source_type'])) {
            $builder->where('d.source_type', (string) $query->filters['source_type']);
        }
        if (isset($query->filters['source_public_id'])) {
            $builder->where('d.source_public_id', (string) $query->filters['source_public_id']);
        }
        if (isset($query->filters['document_ids'])) {
            $ids = array_values(array_filter(array_map('strval', (array) $query->filters['document_ids'])));
            if ($ids !== []) {
                $builder->whereIn('d.public_id', $ids);
            }
        }
        if (isset($query->filters['updated_after'])) {
            $builder->where('k.source_updated_at', '>=', (string) $query->filters['updated_after']);
        }

        return $builder;
    }

    /** @return array<string,mixed> */
    private function decodeMetadata(mixed $metadata): array
    {
        if (is_array($metadata)) {
            return $metadata;
        }
        if (! is_string($metadata) || trim($metadata) === '') {
            return [];
        }

        $decoded = json_decode($metadata, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function estimateTokens(string $content): int
    {
        return max(1, (int) ceil(strlen($content) / 4));
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}
