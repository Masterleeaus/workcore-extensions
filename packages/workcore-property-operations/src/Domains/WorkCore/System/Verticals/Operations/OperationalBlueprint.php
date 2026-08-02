<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Verticals\Operations;

final readonly class OperationalBlueprint
{
    /**
     * @param list<string> $verticalKeys
     * @param list<array<string,mixed>> $categories
     * @param list<array<string,mixed>> $services
     * @param list<array<string,mixed>> $skills
     * @param list<array<string,mixed>> $forms
     * @param list<array<string,mixed>> $complianceRequirements
     */
    public function __construct(
        public array $verticalKeys,
        public array $categories,
        public array $services,
        public array $skills,
        public array $forms,
        public array $complianceRequirements,
    ) {}

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'vertical_keys' => $this->verticalKeys,
            'categories' => $this->categories,
            'services' => $this->services,
            'skills' => $this->skills,
            'forms' => $this->forms,
            'compliance_requirements' => $this->complianceRequirements,
        ];
    }
}
