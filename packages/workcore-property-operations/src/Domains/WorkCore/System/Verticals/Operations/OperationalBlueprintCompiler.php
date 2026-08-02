<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Verticals\Operations;

use InvalidArgumentException;

final class OperationalBlueprintCompiler
{
    /** @param array<string,mixed> $config */
    public function __construct(private array $config) {}

    /** @param list<string> $addOns @param array<string,mixed> $answers */
    public function compile(string $primaryVertical, array $addOns = [], array $answers = []): OperationalBlueprint
    {
        if (in_array($primaryVertical, $addOns, true)) {
            throw new InvalidArgumentException("Vertical [{$primaryVertical}] cannot be both primary and add-on.");
        }

        $verticalKeys = array_values(array_unique(array_merge([$primaryVertical], array_map('strval', $addOns))));
        $categories = [];
        $services = [];
        $skills = [];
        $forms = [];
        $compliance = [];

        foreach ($verticalKeys as $verticalKey) {
            $definition = (array) (($this->config['verticals'] ?? [])[$verticalKey] ?? []);
            if ($definition === []) {
                continue;
            }

            $category = (array) ($definition['category'] ?? []);
            if ($category !== []) {
                $categories[(string) $category['key']] = $category + ['vertical_key' => $verticalKey];
            }

            $answerKey = (string) ($definition['service_answer_key'] ?? '');
            $selected = array_values(array_unique(array_map('strval', (array) ($answers[$answerKey] ?? []))));
            $available = (array) ($definition['services'] ?? []);
            foreach ($selected as $serviceType) {
                if (! isset($available[$serviceType]) || ! is_array($available[$serviceType])) {
                    throw new InvalidArgumentException("Unknown service type [{$serviceType}] for vertical [{$verticalKey}].");
                }
                $service = $available[$serviceType];
                $services[] = $service + [
                    'key' => $verticalKey . '.' . $serviceType,
                    'service_type' => $serviceType,
                    'vertical_key' => $verticalKey,
                    'category_key' => (string) ($category['key'] ?? $verticalKey),
                    'business_line_key' => (string) (((array) ($definition['business_line_map'] ?? []))[$serviceType] ?? ''),
                ];
            }

            foreach ((array) ($definition['skills'] ?? []) as $skill) {
                if (! is_array($skill) || empty($skill['key'])) {
                    continue;
                }
                $skills[(string) $skill['key']] = $skill + ['vertical_key' => $verticalKey];
            }

            foreach ((array) ($definition['forms'] ?? []) as $form) {
                if (! is_array($form) || empty($form['key'])) {
                    continue;
                }
                $forms[(string) $form['key']] = $form + ['vertical_key' => $verticalKey];
            }

            foreach ((array) ($definition['conditional_forms'] ?? []) as $conditional) {
                if (! is_array($conditional) || ! $this->conditionMatches($conditional, $answers)) {
                    continue;
                }
                $form = (array) ($conditional['form'] ?? []);
                if (! empty($form['key'])) {
                    $forms[(string) $form['key']] = $form + ['vertical_key' => $verticalKey];
                }
            }

            foreach ((array) ($definition['compliance_requirements'] ?? []) as $requirement) {
                if (! is_array($requirement) || ! $this->conditionMatches($requirement, $answers)) {
                    continue;
                }
                $item = (array) ($requirement['requirement'] ?? $requirement);
                unset($item['when_key'], $item['equals'], $item['requirement']);
                if (! empty($item['key'])) {
                    $compliance[(string) $item['key']] = $item + ['vertical_key' => $verticalKey];
                }
            }
        }

        return new OperationalBlueprint(
            verticalKeys: $verticalKeys,
            categories: array_values($categories),
            services: $services,
            skills: array_values($skills),
            forms: array_values($forms),
            complianceRequirements: array_values($compliance),
        );
    }

    /** @param array<string,mixed> $condition @param array<string,mixed> $answers */
    private function conditionMatches(array $condition, array $answers): bool
    {
        $key = (string) ($condition['when_key'] ?? '');
        if ($key === '') {
            return true;
        }
        return ($answers[$key] ?? null) === ($condition['equals'] ?? true);
    }
}
