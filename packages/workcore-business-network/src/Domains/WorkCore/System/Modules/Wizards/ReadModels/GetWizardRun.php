<?php

declare(strict_types=1);
namespace App\Domains\WorkCore\System\Modules\Wizards\ReadModels;
use App\Domains\WorkCore\System\Modules\Wizards\Services\WizardRuntime;
final class GetWizardRun { public function __construct(private WizardRuntime $runtime){} public function execute(string $runPublicId,int $companyId): array { return $this->runtime->review($runPublicId,$companyId); } }
