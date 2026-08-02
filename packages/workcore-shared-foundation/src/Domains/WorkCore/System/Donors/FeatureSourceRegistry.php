<?php

declare(strict_types=1);
namespace App\Domains\WorkCore\System\Donors;
final class FeatureSourceRegistry
{
    private array $definitions=[];
    public function register(FeatureSourceDefinition $definition): void{if(isset($this->definitions[$definition->feature])){throw new \InvalidArgumentException("Feature source [{$definition->feature}] already registered.");}$this->definitions[$definition->feature]=$definition;}
    public function all(): array{ksort($this->definitions);return array_values($this->definitions);}
    public function get(string $feature): ?FeatureSourceDefinition{return $this->definitions[$feature]??null;}
}
