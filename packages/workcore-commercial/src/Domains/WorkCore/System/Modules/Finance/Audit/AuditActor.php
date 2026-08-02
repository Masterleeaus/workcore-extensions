<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Audit;

use InvalidArgumentException;

final readonly class AuditActor
{
    public function __construct(
        public ActorType $type,
        public string $id,
        public ?string $label = null,
    ) {
        if (trim($id) === '') {
            throw new InvalidArgumentException('Audit actor ID must not be blank.');
        }
    }

    public static function system(string $id, ?string $label = null): self
    {
        return new self(ActorType::System, $id, $label);
    }

    /** @return array{type:string,id:string,label:string|null} */
    public function toArray(): array
    {
        return [
            'type' => $this->type->value,
            'id' => $this->id,
            'label' => $this->label,
        ];
    }
}
