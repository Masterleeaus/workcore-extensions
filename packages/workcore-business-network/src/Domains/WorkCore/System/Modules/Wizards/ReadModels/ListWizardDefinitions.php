<?php

declare(strict_types=1);namespace App\Domains\WorkCore\System\Modules\Wizards\ReadModels;use App\Domains\WorkCore\System\Modules\Wizards\Services\WizardDefinitionRegistry;final class ListWizardDefinitions{public function __construct(private WizardDefinitionRegistry $registry){}public function execute(array $filters=[],?int $companyId=null):array{return array_values(array_filter($this->registry->all($companyId),static fn(array $d):bool=>($filters['category']??null)===null||($d['category']??null)===$filters['category']));}}
