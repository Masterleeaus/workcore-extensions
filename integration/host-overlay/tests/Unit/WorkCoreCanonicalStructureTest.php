<?php

it('contains WorkCore only as a canonical domain without staging folders', function () {
    $domain = realpath(__DIR__.'/../../app/Domains/WorkCore');
    expect($domain)->not->toBeFalse();

    foreach (['source', 'sources', 'integration', 'integrations', 'donor', 'extraction', 'staging'] as $forbidden) {
        expect(is_dir($domain.DIRECTORY_SEPARATOR.$forbidden))->toBeFalse();
    }

    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($domain));
    foreach ($files as $file) {
        if (! $file->isFile()) continue;
        expect($file->getPathname())->not->toContain('/source/')->not->toContain('/integration/');
    }
});
