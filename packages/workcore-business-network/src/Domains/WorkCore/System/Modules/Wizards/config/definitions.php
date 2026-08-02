<?php

declare(strict_types=1);

return json_decode((string) file_get_contents(__DIR__ . '/definitions.json'), true, 512, JSON_THROW_ON_ERROR);
