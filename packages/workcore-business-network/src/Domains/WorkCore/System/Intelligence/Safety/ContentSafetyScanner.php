<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Intelligence\Safety;

final class ContentSafetyScanner
{
    public function __construct(
        private SecretScanner $secrets,
        private AustralianIdentifierDetector $australianIdentifiers,
        private bool $redactEmails = true,
    ) {}

    public static function australianDefault(): self
    {
        return new self(new SecretScanner(), new AustralianIdentifierDetector(), true);
    }

    public function scan(string $content, string $trustBoundary = 'external_model'): SafetyScanResult
    {
        $findings = [];
        foreach ($this->secrets->detect($content) as $secret) {
            $action = $secret['type'] === 'private_key' ? 'block' : 'redact';
            $findings[] = new SafetyFinding(
                $secret['type'],
                $action,
                $secret['offset'],
                $secret['length'],
                '[REDACTED:' . $secret['type'] . ']',
                $secret['severity'],
            );
        }

        foreach ($this->australianIdentifiers->detect($content) as $identifier) {
            $findings[] = new SafetyFinding(
                $identifier['type'],
                'redact',
                $identifier['offset'],
                $identifier['length'],
                '[REDACTED:' . $identifier['type'] . ']',
                $identifier['severity'],
            );
        }

        if ($this->redactEmails && preg_match_all('/\b[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}\b/i', $content, $emails, PREG_OFFSET_CAPTURE)) {
            foreach ($emails[0] as [$email, $offset]) {
                $findings[] = new SafetyFinding('email', 'redact', $offset, strlen($email), '[REDACTED:email]', 'medium');
            }
        }

        usort($findings, static fn (SafetyFinding $left, SafetyFinding $right): int => $left->offset <=> $right->offset ?: $right->length <=> $left->length);
        $findings = $this->deduplicateOverlaps($findings);
        $blocked = false;
        foreach ($findings as $finding) {
            if ($finding->action === 'block') {
                $blocked = true;
                break;
            }
        }
        $sanitized = $content;
        if (! $blocked) {
            foreach (array_reverse($findings) as $finding) {
                if ($finding->action !== 'redact') {
                    continue;
                }
                $sanitized = substr($sanitized, 0, $finding->offset)
                    . $finding->replacement
                    . substr($sanitized, $finding->offset + $finding->length);
            }
        }

        return new SafetyScanResult($blocked ? '' : $sanitized, $blocked, $findings, $trustBoundary);
    }

    /** @param list<SafetyFinding> $findings @return list<SafetyFinding> */
    private function deduplicateOverlaps(array $findings): array
    {
        $accepted = [];
        $cursor = -1;
        foreach ($findings as $finding) {
            if ($finding->offset < $cursor) {
                continue;
            }
            $accepted[] = $finding;
            $cursor = $finding->offset + $finding->length;
        }

        return $accepted;
    }
}
