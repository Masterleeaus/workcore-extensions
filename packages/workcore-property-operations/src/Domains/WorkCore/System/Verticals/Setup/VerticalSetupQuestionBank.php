<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Verticals\Setup;

use App\Domains\WorkCore\System\Verticals\VerticalRegistry;
use InvalidArgumentException;

final class VerticalSetupQuestionBank
{
    /** @param array<string,mixed> $config */
    public function __construct(private VerticalRegistry $registry, private array $config = []) {}

    /** @param list<string> $addOns @param array<string,mixed> $answers @return list<array<string,mixed>> */
    public function questions(string $primaryVertical, array $addOns = [], array $answers = []): array
    {
        return array_map(static fn (SetupQuestion $question): array => $question->toArray(), $this->definitions($primaryVertical, $addOns, $answers));
    }

    /** @param list<string> $addOns @param array<string,mixed> $answers @return list<SetupQuestion> */
    public function definitions(string $primaryVertical, array $addOns = [], array $answers = []): array
    {
        $packKeys = $this->selectablePackKeys($primaryVertical, $addOns);
        $definitions = [];
        foreach ((array) ($this->config['questions'] ?? []) as $definition) {
            if (is_array($definition)) {
                $question = SetupQuestion::fromArray($definition);
                $definitions[$question->key] = $question;
            }
        }
        foreach (array_merge([$primaryVertical], $addOns) as $packKey) {
            $setup = $this->registry->get($packKey)->setup;
            foreach ((array) ($setup['questions'] ?? []) as $definition) {
                if (is_array($definition)) {
                    $question = SetupQuestion::fromArray($definition, $packKey);
                    $definitions[$question->key] = $question;
                }
            }
        }
        $documentKeys = $this->availableDocumentKeys($primaryVertical, $addOns);
        if (isset($definitions['documents.enabled'])) {
            $base = $definitions['documents.enabled'];
            $definitions['documents.enabled'] = new SetupQuestion(
                key: $base->key, section: $base->section, prompt: $base->prompt, type: $base->type,
                required: $base->required, options: $documentKeys, default: $base->default,
                help: $base->help, mapsTo: $base->mapsTo, appliesWhen: $base->appliesWhen,
                metadata: $base->metadata, sourcePack: $base->sourcePack,
            );
        }
        return array_values(array_filter($definitions, static fn (SetupQuestion $question): bool => $question->applies($answers, $packKeys)));
    }

    /** @param list<string> $addOns @return list<string> */
    public function availableDocumentKeys(string $primaryVertical, array $addOns = []): array
    {
        $available = (array) ($this->config['document_library'] ?? []);
        $selected = [];
        foreach (array_merge([$primaryVertical], $addOns) as $packKey) {
            foreach ((array) ($this->registry->get($packKey)->setup['documents'] ?? []) as $documentKey) {
                $key = (string) $documentKey;
                if (isset($available[$key])) {
                    $selected[$key] = true;
                }
            }
        }
        return array_keys($selected);
    }

    /** @param list<string> $addOns @return list<string> */
    private function selectablePackKeys(string $primaryVertical, array $addOns): array
    {
        $keys = array_values(array_unique(array_merge([$primaryVertical], array_map('strval', $addOns))));
        foreach ($keys as $key) {
            $definition = $this->registry->get($key);
            if ($definition->type === 'foundation') {
                throw new InvalidArgumentException("Foundation pack [{$key}] cannot be selected directly.");
            }
        }
        return $keys;
    }
}
