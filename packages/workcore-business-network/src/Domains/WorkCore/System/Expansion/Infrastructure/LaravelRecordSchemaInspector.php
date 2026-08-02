<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Expansion\Infrastructure;

use App\Domains\WorkCore\System\Expansion\Contracts\RecordSchemaInspectorContract;
use Illuminate\Database\ConnectionInterface;
use Throwable;

final class LaravelRecordSchemaInspector implements RecordSchemaInspectorContract
{
    public function __construct(private ConnectionInterface $database) {}

    /** @return array<int,string> */
    public function columns(string $table): array
    {
        $table = trim($table);
        if ($table === '') {
            return [];
        }

        try {
            $schema = $this->database->getSchemaBuilder();
            if (! $schema->hasTable($table)) {
                return [];
            }

            return array_values(array_map('strval', $schema->getColumnListing($table)));
        } catch (Throwable) {
            return [];
        }
    }
}
