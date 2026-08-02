<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Intelligence\Safety;

final class SecretScanner
{
    /** @var array<string,string> */
    private const PATTERNS = [
        'named_api_key' => '/(?i)(?:api[_\-]?key|apikey)\s*[:=]\s*[\'\"][^\'\"]{8,}[\'\"]/',
        'named_secret' => '/(?i)(?:secret|password|passwd|pwd)\s*[:=]\s*[\'\"][^\'\"]{4,}[\'\"]/',
        'named_token' => '/(?i)(?:token|bearer)\s*[:=]\s*[\'\"][^\'\"]{10,}[\'\"]/',
        'private_key' => '/-----BEGIN (?:RSA |EC |DSA |OPENSSH )?PRIVATE KEY-----/',
        'provider_key' => '/\b(?:sk|pk)-[a-zA-Z0-9_-]{20,}\b/',
        'github_token' => '/\bgh[pousr]_[a-zA-Z0-9]{30,255}\b/',
        'npm_token' => '/\bnpm_[a-zA-Z0-9]{30,255}\b/',
        'aws_access_key' => '/\b(?:AKIA|ASIA)[0-9A-Z]{16}\b/',
        'jwt' => '/\beyJ[a-zA-Z0-9_-]{8,}\.[a-zA-Z0-9_-]{8,}\.[a-zA-Z0-9_-]{8,}\b/',
    ];

    /** @return list<array{type:string,offset:int,length:int,redacted:string,severity:string}> */
    public function detect(string $content): array
    {
        $findings = [];
        foreach (self::PATTERNS as $type => $pattern) {
            $count = preg_match_all($pattern, $content, $matches, PREG_OFFSET_CAPTURE);
            if ($count === false || $count === 0) {
                continue;
            }
            foreach ($matches[0] ?? [] as [$matched, $offset]) {
                $findings[] = [
                    'type' => $type,
                    'offset' => $offset,
                    'length' => strlen($matched),
                    'redacted' => '[REDACTED:' . $type . ']',
                    'severity' => in_array($type, ['private_key', 'aws_access_key', 'provider_key'], true) ? 'critical' : 'high',
                ];
            }
        }

        usort($findings, static fn (array $left, array $right): int => $left['offset'] <=> $right['offset'] ?: $right['length'] <=> $left['length']);

        return $this->deduplicateOverlaps($findings);
    }

    public function redactText(string $content): string
    {
        $rewritten = $content;
        foreach (array_reverse($this->detect($content)) as $finding) {
            $rewritten = substr($rewritten, 0, $finding['offset'])
                . $finding['redacted']
                . substr($rewritten, $finding['offset'] + $finding['length']);
        }

        return $rewritten;
    }

    public static function redact(string $value): string
    {
        return str_repeat('*', strlen($value));
    }

    /**
     * @param list<array{type:string,offset:int,length:int,redacted:string,severity:string}> $findings
     * @return list<array{type:string,offset:int,length:int,redacted:string,severity:string}>
     */
    private function deduplicateOverlaps(array $findings): array
    {
        $accepted = [];
        $cursor = -1;
        foreach ($findings as $finding) {
            if ($finding['offset'] < $cursor) {
                continue;
            }
            $accepted[] = $finding;
            $cursor = $finding['offset'] + $finding['length'];
        }

        return $accepted;
    }
}
