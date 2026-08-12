<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

$config = new Configuration();

return $config
    ->ignoreErrorsOnPackages(
        [
            'laminas/laminas-cli',
            'psr/log',
            'symfony/console',
        ],
        [ErrorType::DEV_DEPENDENCY_IN_PROD]
    )
    ->ignoreUnknownClasses([
        Mezzio\Tooling\Routes\ConfigLoaderInterface::class,
        Mezzio\Tooling\Routes\ListRoutesCommand::class,
    ]);
