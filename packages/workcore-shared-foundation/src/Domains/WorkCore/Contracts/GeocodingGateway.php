<?php
declare(strict_types=1);
namespace App\Domains\WorkCore\Contracts;
interface GeocodingGateway { public function geocode(string $address): array; }
