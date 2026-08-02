<?php

declare(strict_types=1);
namespace App\Domains\WorkCore\System\Modules\CRM\Data;
use App\Domains\WorkCore\System\Modules\CRM\Support\CrmNormalizer;
final readonly class CustomerData
{
    public function __construct(public string $name,public ?string $email=null,public ?string $companyName=null,public ?string $phone=null,public ?string $address=null,public ?string $note=null) {}
    public static function fromArray(array $data): self { return new self(trim((string)$data['name']),CrmNormalizer::email($data['email']??null),isset($data['company_name'])?trim((string)$data['company_name']):null,CrmNormalizer::phone($data['phone']??null),isset($data['address'])?trim((string)$data['address']):null,isset($data['note'])?trim((string)$data['note']):null); }
}
