<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Intelligence\Safety;

final class AustralianIdentifierDetector
{
    /** @return list<array{type:string,offset:int,length:int,severity:string}> */
    public function detect(string $content): array
    {
        $matches = [];
        $this->collectValidated($content, '/(?<!\d)(\d{3}[ ]?\d{3}[ ]?\d{3})(?!\d)/', 'au_tfn', [$this, 'validTfn'], $matches);
        $this->collectValidated($content, '/(?<!\d)(\d{2}[ ]?\d{3}[ ]?\d{3}[ ]?\d{3})(?!\d)/', 'au_abn', [$this, 'validAbn'], $matches);
        $this->collectValidated($content, '/(?<!\d)(\d{4}[ ]?\d{5}[ ]?\d)(?!\d)/', 'au_medicare', [$this, 'validMedicare'], $matches);

        if (preg_match_all('/(?<!\d)(\d{3}-?\d{3})\D{0,24}(?:account|acct|a\/c)?\D{0,8}(\d{6,10})(?!\d)/i', $content, $bank, PREG_OFFSET_CAPTURE)) {
            foreach ($bank[0] as [$value, $offset]) {
                $matches[] = [
                    'type' => 'au_bank_details',
                    'offset' => $offset,
                    'length' => strlen($value),
                    'severity' => 'high',
                ];
            }
        }

        usort($matches, static fn (array $left, array $right): int => $left['offset'] <=> $right['offset'] ?: $right['length'] <=> $left['length']);

        return $this->deduplicateOverlaps($matches);
    }

    public function validTfn(string $value): bool
    {
        $digits = $this->digits($value);
        if (strlen($digits) !== 9) {
            return false;
        }
        $weights = [1, 4, 3, 7, 5, 8, 6, 9, 10];
        $sum = 0;
        foreach ($weights as $index => $weight) {
            $sum += ((int) $digits[$index]) * $weight;
        }

        return $sum % 11 === 0;
    }

    public function validAbn(string $value): bool
    {
        $digits = $this->digits($value);
        if (strlen($digits) !== 11) {
            return false;
        }
        $weights = [10, 1, 3, 5, 7, 9, 11, 13, 15, 17, 19];
        $sum = (((int) $digits[0]) - 1) * $weights[0];
        for ($index = 1; $index < 11; $index++) {
            $sum += ((int) $digits[$index]) * $weights[$index];
        }

        return $sum % 89 === 0;
    }

    public function validMedicare(string $value): bool
    {
        $digits = $this->digits($value);
        if (strlen($digits) !== 10) {
            return false;
        }
        $weights = [1, 3, 7, 9, 1, 3, 7, 9];
        $sum = 0;
        foreach ($weights as $index => $weight) {
            $sum += ((int) $digits[$index]) * $weight;
        }

        return $sum % 10 === (int) $digits[8] && (int) $digits[9] > 0;
    }

    /**
     * @param callable(string):bool $validator
     * @param list<array{type:string,offset:int,length:int,severity:string}> $out
     */
    private function collectValidated(string $content, string $pattern, string $type, callable $validator, array &$out): void
    {
        if (! preg_match_all($pattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
            return;
        }
        foreach ($matches[1] as [$value, $offset]) {
            if (! $validator($value)) {
                continue;
            }
            $out[] = [
                'type' => $type,
                'offset' => $offset,
                'length' => strlen($value),
                'severity' => 'high',
            ];
        }
    }

    private function digits(string $value): string
    {
        return preg_replace('/\D+/', '', $value) ?? '';
    }

    /**
     * @param list<array{type:string,offset:int,length:int,severity:string}> $matches
     * @return list<array{type:string,offset:int,length:int,severity:string}>
     */
    private function deduplicateOverlaps(array $matches): array
    {
        $accepted = [];
        $cursor = -1;
        foreach ($matches as $match) {
            if ($match['offset'] < $cursor) {
                continue;
            }
            $accepted[] = $match;
            $cursor = $match['offset'] + $match['length'];
        }

        return $accepted;
    }
}
