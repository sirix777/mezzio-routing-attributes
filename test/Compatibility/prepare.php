<?php

declare(strict_types=1);

// Each mode gets its own Composer autoloader: unit-test Tooling stubs are never loaded.
$root      = \dirname(__DIR__, 2);
$mode      = $argv[1] ?? '';
$directory = $argv[2] ?? '';
if (! \in_array($mode, ['tooling', 'without-tooling', 'runtime-lowest', 'dev-lowest'], true) || ! \is_dir($directory)) {
    throw new RuntimeException('Usage: php prepare.php tooling|without-tooling|runtime-lowest|dev-lowest EMPTY_DIRECTORY');
}

if (\scandir($directory) !== ['.', '..']) {
    throw new RuntimeException('The target directory must be empty.');
}
$source = \file_get_contents($root . '/composer.json');
if (false === $source) {
    throw new RuntimeException('Cannot read package composer.json');
}
$package      = \json_decode($source, true, 512, JSON_THROW_ON_ERROR);
$requirements = $package['require'];
if (\in_array($mode, ['tooling', 'without-tooling'], true)) {
    $requirements += [
        'laminas/laminas-cli'            => '^1.15',
        'laminas/laminas-diactoros'      => '^3.8',
        'laminas/laminas-servicemanager' => '^3.23',
        'mezzio/mezzio'                  => '^3.13',
        'sirix/mezzio-radixrouter'       => $package['require-dev']['sirix/mezzio-radixrouter'],
        'symfony/console'                => '^6.4 || ^7.0',
    ];
}

if ('dev-lowest' === $mode) {
    $requirements += $package['require-dev'];
    // QA tools have their own Composer environments and are checked by the QA matrix.
    unset($requirements['bamarni/composer-bin-plugin']);
}

if ('tooling' === $mode) {
    // Tooling 2.13 supports PHP 8.1–8.4 and Router 3, so it has a dedicated job.
    $requirements['mezzio/mezzio-tooling'] = '~2.13.0';
}
$manifest = \json_encode([
    'name'     => 'sirix/routing-attributes-compatibility',
    'license'  => 'MIT',
    'require'  => $requirements,
    'autoload' => [
        'psr-4' => [
            'Sirix\Mezzio\Routing\Attributes\\'     => $root . '/src/',
            'SirixTest\Mezzio\Routing\Attributes\\' => $root . '/test/',
        ],
    ],
    'config'   => [
        'allow-plugins' => false,
    ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
if (\file_put_contents($directory . '/composer.json', $manifest) !== \strlen($manifest)) {
    throw new RuntimeException('Cannot write compatibility composer.json');
}
