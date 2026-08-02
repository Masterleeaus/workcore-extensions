<?php declare(strict_types=1); namespace App\Domains\WorkCore\System\Contracts\Records; interface ReviewAccessContract { public function find(int $companyId,string $publicId): ?array; }
