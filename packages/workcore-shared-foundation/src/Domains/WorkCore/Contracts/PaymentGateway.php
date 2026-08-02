<?php
declare(strict_types=1);
namespace App\Domains\WorkCore\Contracts;
interface PaymentGateway { public function record(array $payload): string; }
