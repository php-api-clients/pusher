<?php declare(strict_types=1);

use ApiClients\Tools\TestUtilities\PhpCsFixerConfig;

return (function ()
{
    $path = __DIR__ . DIRECTORY_SEPARATOR;
    $paths = [
        $path . 'src',
        $path . 'tests',
    ];

    return PhpCsFixerConfig::create()
        ->setFinder(
            PhpCsFixer\Finder::create()
                ->in($paths)
                ->append($paths)
        )
        ->setUsingCache(false)
    ;
})();
