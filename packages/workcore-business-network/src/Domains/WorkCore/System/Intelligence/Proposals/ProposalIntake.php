<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Intelligence\Proposals;

use InvalidArgumentException;

final class ProposalIntake
{
    private const EXECUTABLE_PATTERNS = [
        '/<\?php/i',
        '/\b(?:system|exec|shell_exec|passthru|proc_open|popen)\s*\(/i',
        '/\b(?:DROP|ALTER|TRUNCATE)\s+(?:TABLE|DATABASE)\b/i',
        '/\brm\s+-rf\b/i',
        '/\b(?:powershell|cmd\.exe|\/bin\/sh|\/bin\/bash)\b/i',
    ];

    public function __construct(
        private BalancedJsonExtractor $extractor,
        private StrictSchemaValidator $validator,
        private int $maxBytes = 100000,
    ) {}

    /** @param array<string,mixed> $schema */
    public function accept(string $modelOutput, array $schema, int $companyId, int $actorId, string $proposalType): ProposalEnvelope
    {
        if ($companyId < 1 || $actorId < 1 || trim($proposalType) === '') {
            throw new InvalidArgumentException('Company, actor and proposal type are required.');
        }
        if (strlen($modelOutput) > $this->maxBytes) {
            throw new InvalidArgumentException('Model proposal exceeds the configured size limit.');
        }

        $json = $this->extractor->extract($modelOutput);
        $payload = json_decode($json, true, 128, JSON_THROW_ON_ERROR);
        if (! is_array($payload) || array_is_list($payload)) {
            throw new InvalidArgumentException('Model proposal must be a JSON object.');
        }
        $errors = $this->validator->validate($payload, $schema);
        if ($errors !== []) {
            throw new InvalidArgumentException('Model proposal failed schema validation: ' . implode('; ', array_slice($errors, 0, 12)));
        }
        $this->assertNonExecutable($payload);
        $canonical = $this->canonicalize($payload);

        return new ProposalEnvelope(
            payload: $canonical,
            payloadHash: hash('sha256', json_encode($canonical, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)),
            companyId: $companyId,
            actorId: $actorId,
            proposalType: trim($proposalType),
            receivedAt: gmdate('c'),
        );
    }

    /** @param mixed $value */
    private function assertNonExecutable(mixed $value): void
    {
        if (is_array($value)) {
            foreach ($value as $child) {
                $this->assertNonExecutable($child);
            }
            return;
        }
        if (! is_string($value)) {
            return;
        }
        foreach (self::EXECUTABLE_PATTERNS as $pattern) {
            if (preg_match($pattern, $value) === 1) {
                throw new InvalidArgumentException('Model proposal contains executable or destructive content.');
            }
        }
    }

    /** @param mixed $value @return mixed */
    private function canonicalize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }
        if (array_is_list($value)) {
            return array_map(fn (mixed $item): mixed => $this->canonicalize($item), $value);
        }
        ksort($value);
        foreach ($value as $key => $item) {
            $value[$key] = $this->canonicalize($item);
        }

        return $value;
    }
}
