<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Intelligence\Proposals;

use InvalidArgumentException;

final class BalancedJsonExtractor
{
    public function extract(string $content): string
    {
        $content = trim($content);
        if ($content === '') {
            throw new InvalidArgumentException('Structured output is empty.');
        }

        $length = strlen($content);
        for ($start = 0; $start < $length; $start++) {
            if ($content[$start] !== '{' && $content[$start] !== '[') {
                continue;
            }

            $candidate = $this->balancedCandidate($content, $start);
            if ($candidate === null) {
                continue;
            }

            try {
                json_decode($candidate, true, 128, JSON_THROW_ON_ERROR);
                return $candidate;
            } catch (\JsonException) {
                continue;
            }
        }

        throw new InvalidArgumentException('No complete JSON object or array was found in the model output.');
    }

    private function balancedCandidate(string $content, int $start): ?string
    {
        $stack = [];
        $quote = null;
        $escaped = false;
        $length = strlen($content);

        for ($index = $start; $index < $length; $index++) {
            $character = $content[$index];
            if ($quote !== null) {
                if ($escaped) {
                    $escaped = false;
                } elseif ($character === '\\') {
                    $escaped = true;
                } elseif ($character === $quote) {
                    $quote = null;
                }
                continue;
            }

            if ($character === '"') {
                $quote = '"';
                continue;
            }
            if ($character === '{' || $character === '[') {
                $stack[] = $character;
                continue;
            }
            if ($character !== '}' && $character !== ']') {
                continue;
            }

            $open = array_pop($stack);
            if (($open === '{' && $character !== '}') || ($open === '[' && $character !== ']')) {
                return null;
            }
            if ($stack === []) {
                return substr($content, $start, $index - $start + 1);
            }
        }

        return null;
    }
}
