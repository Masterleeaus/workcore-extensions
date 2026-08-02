<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Support;

final readonly class DependencyDiagnostics
{
    /**
     * @param array<string, class-string> $required
     * @param array<string, class-string> $optional
     */
    public function __construct(
        private array $required,
        private array $optional,
    ) {
    }

    /** @return array<string, mixed> */
    public function report(): array
    {
        $required = $this->inspect($this->required);
        $optional = $this->inspect($this->optional);

        return [
            'ready' => ! in_array(false, array_column($required, 'available'), true),
            'required' => $required,
            'optional' => $optional,
        ];
    }

    /**
     * @param array<string, class-string> $dependencies
     * @return array<string, array{class:string,available:bool}>
     */
    private function inspect(array $dependencies): array
    {
        $result = [];

        foreach ($dependencies as $key => $class) {
            $result[$key] = [
                'class' => $class,
                'available' => class_exists($class) || interface_exists($class),
            ];
        }

        return $result;
    }
}
