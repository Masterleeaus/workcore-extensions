<?php
declare(strict_types=1);
namespace App\Domains\WorkCore\Contracts;
interface DocumentSigningGateway { public function requestSignature(array $payload): string; }
