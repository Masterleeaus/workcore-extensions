<?php

declare(strict_types=1);

namespace App\Extensions\WorkCore\System\Runtime;

use RuntimeException;

final class WorkCoreRuntimeAutoloader
{
    private const PREFIX = 'App\\Domains\\WorkCore\\';

    /** @var array<string,true> */
    private static array $registeredRoots = [];

    public static function register(string $runtimeRoot): void
    {
        $runtimeRoot = rtrim($runtimeRoot, DIRECTORY_SEPARATOR);
        $domainRoot = $runtimeRoot . DIRECTORY_SEPARATOR . 'Domains' . DIRECTORY_SEPARATOR . 'WorkCore';

        if (! is_dir($domainRoot)) {
            throw new RuntimeException("WorkCore runtime root does not exist: {$domainRoot}");
        }

        $realRoot = realpath($runtimeRoot);
        if ($realRoot === false || isset(self::$registeredRoots[$realRoot])) {
            return;
        }

        self::$registeredRoots[$realRoot] = true;

        spl_autoload_register(
            static function (string $class) use ($realRoot): void {
                if (! str_starts_with($class, self::PREFIX)) {
                    return;
                }

                $relative = substr($class, strlen('App\\'));
                $file = $realRoot . DIRECTORY_SEPARATOR
                    . str_replace('\\', DIRECTORY_SEPARATOR, $relative)
                    . '.php';

                if (is_file($file)) {
                    require_once $file;
                }
            },
            prepend: true,
        );
    }
}
