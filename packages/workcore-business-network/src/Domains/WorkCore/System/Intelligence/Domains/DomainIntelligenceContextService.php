<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Intelligence\Domains;

use InvalidArgumentException;

final readonly class DomainIntelligenceContextService
{
    public function __construct(private DomainIntelligenceRegistry $registry) {}

    public function build(string $domain, DomainIntelligenceRequest $request): DomainIntelligenceContext
    {
        return $this->registry->require(trim($domain))->context($request);
    }

    /**
     * @param list<string> $domains
     * @return array<string,DomainIntelligenceContext>
     */
    public function buildMany(array $domains, DomainIntelligenceRequest $request): array
    {
        if (count($domains) > 20) {
            throw new InvalidArgumentException('At most twenty domain contexts may be assembled at once.');
        }

        $contexts = [];
        foreach (array_values(array_unique(array_map('trim', $domains))) as $domain) {
            if ($domain === '') {
                continue;
            }
            $contexts[$domain] = $this->build($domain, $request);
        }

        return $contexts;
    }
}
