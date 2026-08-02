<?php
declare(strict_types=1);
namespace App\Domains\WorkCore\Contracts;
interface MessagingGateway { public function send(array $payload): string; }
