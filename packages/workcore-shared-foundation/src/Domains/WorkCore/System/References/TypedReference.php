<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\References;

use InvalidArgumentException;
use Stringable;

final readonly class TypedReference implements Stringable
{
    public function __construct(public string $type, public string $id)
    {
        if (preg_match('/^[a-z][a-z0-9_.-]*$/', $type) !== 1) {
            throw new InvalidArgumentException('Reference type must be a stable lowercase key.');
        }
        if (trim($id) === '') {
            throw new InvalidArgumentException('Reference ID is required.');
        }
    }

    /** @return array{type:string,id:string} */
    public function toArray(): array { return ['type' => $this->type, 'id' => $this->id]; }
    public function __toString(): string { return $this->type . ':' . $this->id; }
}
