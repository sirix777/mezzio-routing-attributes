<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Php74\Rector\Closure\ClosureToArrowFunctionRector;
use Rector\Php55\Rector\String_\StringClassNameToClassConstantRector;
use Rector\Set\ValueObject\LevelSetList;
use Rector\Set\ValueObject\SetList;

return static function(RectorConfig $rectorConfig): void {
    $rectorConfig->parallel(processTimeout: 360);

    $rectorConfig->paths([
        __DIR__ . '/src',
        __DIR__ . '/test',
    ]);

    $rectorConfig->sets([
        SetList::CODE_QUALITY,
        SetList::PRIVATIZATION,
        SetList::DEAD_CODE,
        SetList::EARLY_RETURN,
        LevelSetList::UP_TO_PHP_82,
    ]);

    $rectorConfig->skip([
        StringClassNameToClassConstantRector::class => [
            __DIR__ . '/src/Command/ConsoleRegistrationPolicy.php',
            __DIR__ . '/src/ConfigProvider.php',
            __DIR__ . '/src/Command/ListRoutesCommandFactory.php',
            __DIR__ . '/test/ConfigProviderConsoleRegistrationTest.php',
            __DIR__ . '/test/ConfigProviderTest.php',
            __DIR__ . '/test/Command/ListRoutesCommandDelegatorTest.php',
            __DIR__ . '/test/Command/ListRoutesCommandFactoryTest.php',
        ],
    ]);
};
