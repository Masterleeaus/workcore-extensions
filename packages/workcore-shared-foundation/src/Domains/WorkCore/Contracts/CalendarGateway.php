<?php
declare(strict_types=1);
namespace App\Domains\WorkCore\Contracts;
interface CalendarGateway { public function schedule(array $payload): string; }
