<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Verticals\ReadModels;

use App\Domains\WorkCore\System\Verticals\Setup\VerticalSetupQuestionBank;
use App\Domains\WorkCore\System\Verticals\VerticalRegistry;

final class ListVerticalCatalogue
{
    public function __construct(
        private VerticalRegistry $registry,
        private VerticalSetupQuestionBank $questions,
    ) {}

    /** @param list<string> $addOns @param array<string,mixed> $answers @return array<string,mixed> */
    public function execute(?string $primaryVertical = null, array $addOns = [], array $answers = []): array
    {
        $verticals = [];
        foreach ($this->registry->all() as $definition) {
            if ($definition->type === 'foundation') {
                continue;
            }
            $verticals[] = [
                'key' => $definition->key,
                'label' => $definition->label,
                'version' => $definition->version,
                'type' => $definition->type,
                'extends' => $definition->extends,
                'capabilities' => $definition->capabilities,
                'features' => $definition->features,
                'default_business_lines' => array_values((array) ($definition->setup['default_business_lines'] ?? [])),
                'starter_documents' => array_values(array_map('strval', (array) ($definition->setup['starter_documents'] ?? []))),
                'metadata' => $definition->metadata,
            ];
        }

        return [
            'verticals' => $verticals,
            'questions' => $primaryVertical === null || $primaryVertical === ''
                ? []
                : $this->questions->questions($primaryVertical, $addOns, $answers),
        ];
    }
}
