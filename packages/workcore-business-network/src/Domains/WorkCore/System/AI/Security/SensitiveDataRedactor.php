<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\AI\Security;

final class SensitiveDataRedactor
{
    /** @param list<string> $sensitiveKeys */
    public function __construct(private array $sensitiveKeys = [])
    {
        $this->sensitiveKeys = array_map(static fn (string $key): string => strtolower(trim($key)), $sensitiveKeys);
    }

    /** @param array<string,mixed> $payload @return array<string,mixed> */
    public function redact(array $payload): array
    {
        $redacted = [];
        foreach ($payload as $key => $value) {
            if ($this->isSensitive((string) $key)) {
                $redacted[$key] = '[REDACTED]';
                continue;
            }
            $redacted[$key] = is_array($value) ? $this->redact($value) : $value;
        }
        return $redacted;
    }

    private function isSensitive(string $key): bool
    {
        $key = strtolower($key);
        foreach ($this->sensitiveKeys as $sensitive) {
            if ($key === $sensitive || str_contains($key, $sensitive)) {
                return true;
            }
        }
        return false;
    }
}
