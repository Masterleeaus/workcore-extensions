<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Verticals\TradeCompliance;

use InvalidArgumentException;

final class TradeCompliancePolicy
{
    /** @param list<string> $allowedTypes */
    public function __construct(private array $allowedTypes) {}

    /**
     * @param list<array<string,mixed>> $profiles
     * @return array<string,mixed>|null
     */
    public function selectProfile(array $profiles, ?int $businessLineId, ?string $serviceCode, string $effectiveOn): ?array
    {
        $matches = [];
        foreach ($profiles as $profile) {
            if (($profile['status'] ?? 'active') !== 'active') {
                continue;
            }
            $from = trim((string) ($profile['effective_from'] ?? ''));
            $to = trim((string) ($profile['effective_to'] ?? ''));
            if ($from !== '' && $from > $effectiveOn) {
                continue;
            }
            if ($to !== '' && $to < $effectiveOn) {
                continue;
            }

            $profileLine = $profile['business_line_id'] ?? null;
            if ($profileLine !== null && (int) $profileLine !== $businessLineId) {
                continue;
            }
            $profileService = trim((string) ($profile['service_code'] ?? ''));
            if ($profileService !== '' && $profileService !== trim((string) $serviceCode)) {
                continue;
            }

            $score = 0;
            if ($profileLine !== null) {
                $score += 10;
            }
            if ($profileService !== '') {
                $score += 5;
            }
            $profile['_selection_score'] = $score;
            $matches[] = $profile;
        }

        usort($matches, static function (array $left, array $right): int {
            $score = ((int) ($right['_selection_score'] ?? 0)) <=> ((int) ($left['_selection_score'] ?? 0));
            if ($score !== 0) {
                return $score;
            }

            return strcmp((string) ($right['effective_from'] ?? ''), (string) ($left['effective_from'] ?? ''));
        });

        if ($matches === []) {
            return null;
        }
        unset($matches[0]['_selection_score']);

        return $matches[0];
    }

    /**
     * @param list<array<string,mixed>> $requirements
     * @return list<array{code:string,type:string,label:string,required:bool,settings:array<string,mixed>}>
     */
    public function normaliseRequirements(array $requirements): array
    {
        $normalised = [];
        $codes = [];
        foreach ($requirements as $index => $requirement) {
            $code = trim((string) ($requirement['code'] ?? ''));
            $type = trim((string) ($requirement['type'] ?? ''));
            $label = trim((string) ($requirement['label'] ?? ''));
            if ($code === '' || ! preg_match('/^[a-z0-9][a-z0-9_.-]{1,119}$/', $code)) {
                throw new InvalidArgumentException("Requirement at index {$index} has an invalid code.");
            }
            if (isset($codes[$code])) {
                throw new InvalidArgumentException("Requirement code [{$code}] is duplicated.");
            }
            if (! in_array($type, $this->allowedTypes, true)) {
                throw new InvalidArgumentException("Requirement [{$code}] has unsupported type [{$type}].");
            }
            if ($label === '') {
                throw new InvalidArgumentException("Requirement [{$code}] requires a label.");
            }
            $codes[$code] = true;
            $normalised[] = [
                'code' => $code,
                'type' => $type,
                'label' => $label,
                'required' => (bool) ($requirement['required'] ?? true),
                'settings' => is_array($requirement['settings'] ?? null) ? $requirement['settings'] : [],
            ];
        }

        return $normalised;
    }

    /**
     * @param list<array<string,mixed>> $items
     * @return array{ready:bool,legacy_unprepared:bool,total:int,required:int,satisfied:int,waived:int,blocking_codes:list<string>}
     */
    public function gateStatus(array $items): array
    {
        if ($items === []) {
            return [
                'ready' => true,
                'legacy_unprepared' => true,
                'total' => 0,
                'required' => 0,
                'satisfied' => 0,
                'waived' => 0,
                'blocking_codes' => [],
            ];
        }

        $required = 0;
        $satisfied = 0;
        $waived = 0;
        $blocking = [];
        foreach ($items as $item) {
            $status = (string) ($item['status'] ?? 'pending');
            $isRequired = (bool) ($item['required'] ?? false);
            if ($isRequired) {
                $required++;
            }
            if ($status === 'satisfied') {
                $satisfied++;
            } elseif ($status === 'waived') {
                $waived++;
            }
            if ($isRequired && ! in_array($status, ['satisfied', 'waived'], true)) {
                $blocking[] = (string) ($item['requirement_code'] ?? 'unknown');
            }
        }

        return [
            'ready' => $blocking === [],
            'legacy_unprepared' => false,
            'total' => count($items),
            'required' => $required,
            'satisfied' => $satisfied,
            'waived' => $waived,
            'blocking_codes' => $blocking,
        ];
    }
}
