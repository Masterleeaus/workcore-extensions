<?php
declare(strict_types=1);
namespace App\Domains\WorkCore\Contracts;
interface FileStorageGateway { public function storePrivate(string $companyId, string $path, mixed $contents): string; }
