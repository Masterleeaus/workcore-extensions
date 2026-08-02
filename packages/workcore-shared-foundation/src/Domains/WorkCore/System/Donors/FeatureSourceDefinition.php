<?php

declare(strict_types=1);
namespace App\Domains\WorkCore\System\Donors;
final readonly class FeatureSourceDefinition
{
    public function __construct(public string $feature,public string $canonicalDomain,public string $canonicalRecord,public array $donorSources,public string $disposition,public array $notes=[]){if($feature===''||$canonicalDomain===''||$canonicalRecord===''){throw new \InvalidArgumentException('Feature source definitions require feature, domain and record.');}}
    public function toArray(): array{return ['feature'=>$this->feature,'canonical_domain'=>$this->canonicalDomain,'canonical_record'=>$this->canonicalRecord,'donor_sources'=>$this->donorSources,'disposition'=>$this->disposition,'notes'=>$this->notes];}
}
