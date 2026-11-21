<?php

declare(strict_types=1);

namespace SirixTest\Mezzio\Routing\Attributes\Config;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sirix\Mezzio\Routing\Attributes\Config\RoutingAttributesConfig;
use Sirix\Mezzio\Routing\Attributes\Exception\InvalidConfigurationException;

final class RoutingAttributesConfigTest extends TestCase
{
    public function testParsesValidConfiguration(): void
    {
        $config = RoutingAttributesConfig::fromRootConfig([
            'routing_attributes' => [
                'classes'            => ['App\Handler\PingHandler'],
                'duplicate_strategy' => 'ignore',
                'cache'              => [
                    'enabled' => true,
                    'file'    => '/tmp/routes.php',
                ],
                'discovery'          => [
                    'enabled' => true,
                    'paths'   => ['/app/src/Handler'],
                ],
            ],
        ]);

        self::assertSame(['App\Handler\PingHandler'], $config->classes);
        self::assertSame('ignore', $config->duplicateStrategy);
        self::assertSame('psr15', $config->handlersMode);
        self::assertFalse($config->overrideMezzioRoutesListCommand);
        self::assertSame('upstream', $config->classicRoutesMiddlewareDisplay);
        self::assertTrue($config->cacheEnabled);
        self::assertSame('/tmp/routes.php', $config->cacheFile);
        self::assertNull($config->cacheRelease);
        self::assertTrue($config->discoveryEnabled);
        self::assertSame(['/app/src/Handler'], $config->discoveryPaths);
        self::assertSame('token', $config->discoveryStrategy);
        self::assertSame([], $config->discoveryPsr4Mappings);
        self::assertTrue($config->discoveryPsr4FallbackToToken);
    }

    public function testUsesDefaultsWhenOptionalKeysMissing(): void
    {
        $config = RoutingAttributesConfig::fromRootConfig([
            'routing_attributes' => [
                'classes' => [],
            ],
        ]);

        self::assertSame([], $config->classes);
        self::assertSame('throw', $config->duplicateStrategy);
        self::assertSame('psr15', $config->handlersMode);
        self::assertFalse($config->overrideMezzioRoutesListCommand);
        self::assertSame('upstream', $config->classicRoutesMiddlewareDisplay);
        self::assertFalse($config->cacheEnabled);
        self::assertNull($config->cacheFile);
        self::assertFalse($config->discoveryEnabled);
        self::assertSame([], $config->discoveryPaths);
        self::assertSame('token', $config->discoveryStrategy);
        self::assertSame([], $config->discoveryPsr4Mappings);
        self::assertTrue($config->discoveryPsr4FallbackToToken);
    }

    public function testCacheFingerprintChangesForRouteProducingConfigurationButNotCacheLocation(): void
    {
        $base = [
            'routing_attributes' => [
                'classes' => ['App\Handler\PingHandler'],
                'cache'   => [
                    'enabled' => true,
                    'file'    => '/cache/first.php',
                ],
            ],
        ];
        $sameRoutesDifferentLocation                                        = $base;
        $sameRoutesDifferentLocation['routing_attributes']['cache']['file'] = '/cache/second.php';
        $changedRoutes                                                      = $base;
        $changedRoutes['routing_attributes']['handlers']                    = [
            'mode' => 'callable',
        ];

        self::assertSame(
            RoutingAttributesConfig::fromRootConfig($base)->cacheFingerprint(),
            RoutingAttributesConfig::fromRootConfig($sameRoutesDifferentLocation)->cacheFingerprint()
        );
        self::assertNotSame(
            RoutingAttributesConfig::fromRootConfig($base)->cacheFingerprint(),
            RoutingAttributesConfig::fromRootConfig($changedRoutes)->cacheFingerprint()
        );
    }

    public function testCacheFingerprintChangesWhenDeploymentReleaseChanges(): void
    {
        $releaseOne = [
            'routing_attributes' => [
                'classes' => ['App\Handler\PingHandler'],
                'cache'   => [
                    'enabled' => true,
                    'file'    => '/cache/routes.php',
                    'release' => 'release-1',
                ],
            ],
        ];
        $releaseTwo                                           = $releaseOne;
        $releaseTwo['routing_attributes']['cache']['release'] = 'release-2';

        self::assertNotSame(
            RoutingAttributesConfig::fromRootConfig($releaseOne)->cacheFingerprint(),
            RoutingAttributesConfig::fromRootConfig($releaseTwo)->cacheFingerprint()
        );
    }

    public function testCacheFingerprintSupportsNonUtf8ConfiguredPaths(): void
    {
        $config = RoutingAttributesConfig::fromRootConfig([
            'routing_attributes' => [
                'discovery' => [
                    'enabled' => true,
                    'paths'   => ["/tmp/\xff"],
                ],
                'cache'     => [
                    'enabled' => true,
                    'file'    => '/tmp/routes.php',
                ],
            ],
        ]);

        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $config->cacheFingerprint());
    }

    public function testThrowsWhenRootConfigIsInvalid(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        RoutingAttributesConfig::fromRootConfig('invalid');
    }

    public function testThrowsWhenRemovedLazyServiceResolutionOptionIsUsed(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        RoutingAttributesConfig::fromRootConfig([
            'routing_attributes' => [
                'classes'                 => [],
                'lazy_service_resolution' => true,
            ],
        ]);
    }

    public function testThrowsWhenRemovedCacheWriteFailStrategyOptionIsUsed(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        RoutingAttributesConfig::fromRootConfig([
            'routing_attributes' => [
                'classes' => [],
                'cache'   => [
                    'enabled'             => true,
                    'file'                => '/tmp/routes.php',
                    'write_fail_strategy' => 'throw',
                ],
            ],
        ]);
    }

    public function testThrowsWhenConfiguredCacheReleaseIsEmpty(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        RoutingAttributesConfig::fromRootConfig([
            'routing_attributes' => [
                'classes' => [],
                'cache'   => [
                    'enabled' => true,
                    'file'    => '/tmp/routes.php',
                    'release' => '',
                ],
            ],
        ]);
    }

    public function testThrowsWhenRemovedCacheStrictOptionIsUsed(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        RoutingAttributesConfig::fromRootConfig([
            'routing_attributes' => [
                'classes' => [],
                'cache'   => [
                    'enabled' => true,
                    'file'    => '/tmp/routes.php',
                    'strict'  => true,
                ],
            ],
        ]);
    }

    public function testThrowsWhenRemovedCacheBackendOptionIsUsed(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        RoutingAttributesConfig::fromRootConfig([
            'routing_attributes' => [
                'classes' => [],
                'cache'   => [
                    'enabled' => true,
                    'file'    => '/tmp/routes.php',
                    'backend' => 'invalid',
                ],
            ],
        ]);
    }

    public function testThrowsWhenRemovedCacheModeOptionIsUsed(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        RoutingAttributesConfig::fromRootConfig([
            'routing_attributes' => [
                'classes' => [],
                'cache'   => [
                    'enabled' => true,
                    'mode'    => 'compiled',
                    'file'    => '/tmp/routes.php',
                ],
            ],
        ]);
    }

    public function testThrowsWhenRemovedDiscoveryClassMapCacheOptionIsUsed(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        RoutingAttributesConfig::fromRootConfig([
            'routing_attributes' => [
                'classes'   => [],
                'discovery' => [
                    'enabled'         => true,
                    'paths'           => ['/app/src/Handler'],
                    'class_map_cache' => [
                        'enabled' => true,
                    ],
                ],
            ],
        ]);
    }

    public function testParsesCallableHandlersMode(): void
    {
        $config = RoutingAttributesConfig::fromRootConfig([
            'routing_attributes' => [
                'classes'  => [],
                'handlers' => [
                    'mode' => 'callable',
                ],
            ],
        ]);

        self::assertSame('callable', $config->handlersMode);
    }

    public function testThrowsWhenHandlersModeInvalid(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        RoutingAttributesConfig::fromRootConfig([
            'routing_attributes' => [
                'classes'  => [],
                'handlers' => [
                    'mode' => 'invalid',
                ],
            ],
        ]);
    }

    public function testParsesResolvedClassicRoutesMiddlewareDisplayMode(): void
    {
        $config = RoutingAttributesConfig::fromRootConfig([
            'routing_attributes' => [
                'classes'    => [],
                'route_list' => [
                    'classic_routes_middleware_display' => 'resolved',
                ],
            ],
        ]);

        self::assertSame('resolved', $config->classicRoutesMiddlewareDisplay);
    }

    public function testParsesMezzioRoutesListCommandOverride(): void
    {
        $config = RoutingAttributesConfig::fromRootConfig([
            'routing_attributes' => [
                'override_mezzio_routes_list_command' => true,
            ],
        ]);

        self::assertTrue($config->overrideMezzioRoutesListCommand);
    }

    public function testThrowsWhenMezzioRoutesListCommandOverrideIsInvalid(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        RoutingAttributesConfig::fromRootConfig([
            'routing_attributes' => [
                'override_mezzio_routes_list_command' => 'true',
            ],
        ]);
    }

    public function testThrowsWhenRouteListTypeIsInvalid(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        RoutingAttributesConfig::fromRootConfig([
            'routing_attributes' => [
                'classes'    => [],
                'route_list' => 'invalid',
            ],
        ]);
    }

    public function testThrowsWhenClassicRoutesMiddlewareDisplayModeIsInvalid(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        RoutingAttributesConfig::fromRootConfig([
            'routing_attributes' => [
                'classes'    => [],
                'route_list' => [
                    'classic_routes_middleware_display' => 'invalid',
                ],
            ],
        ]);
    }

    public function testDoesNotValidateInactiveCacheSubOptions(): void
    {
        $config = RoutingAttributesConfig::fromRootConfig([
            'routing_attributes' => [
                'classes' => [],
                'cache'   => [
                    'enabled'             => false,
                    'mode'                => 'invalid-but-ignored',
                    'backend'             => 'invalid-but-ignored',
                    'strict'              => 'invalid-but-ignored',
                    'write_fail_strategy' => 'invalid-but-ignored',
                ],
            ],
        ]);

        self::assertFalse($config->cacheEnabled);
        self::assertNull($config->cacheFile);
    }

    public function testThrowsWhenRemovedDiscoveryClassMapCacheOptionUsedEvenWhenDiscoveryDisabled(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        RoutingAttributesConfig::fromRootConfig([
            'routing_attributes' => [
                'classes'   => [],
                'discovery' => [
                    'enabled'         => false,
                    'class_map_cache' => [
                        'enabled' => false,
                    ],
                ],
            ],
        ]);
    }

    public function testParsesPsr4DiscoveryConfiguration(): void
    {
        $config = RoutingAttributesConfig::fromRootConfig([
            'routing_attributes' => [
                'classes'   => [],
                'discovery' => [
                    'enabled'  => true,
                    'paths'    => ['/app/src'],
                    'strategy' => 'psr4',
                    'psr4'     => [
                        'mappings'          => [
                            '/app/src' => 'App\\',
                        ],
                        'fallback_to_token' => false,
                    ],
                ],
            ],
        ]);

        self::assertTrue($config->discoveryEnabled);
        self::assertSame('psr4', $config->discoveryStrategy);
        self::assertSame([
            '/app/src' => 'App\\',
        ], $config->discoveryPsr4Mappings);
        self::assertFalse($config->discoveryPsr4FallbackToToken);
    }

    public function testThrowsWhenDiscoveryStrategyInvalid(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        RoutingAttributesConfig::fromRootConfig([
            'routing_attributes' => [
                'classes'   => [],
                'discovery' => [
                    'enabled'  => true,
                    'paths'    => ['/app/src'],
                    'strategy' => 'invalid',
                ],
            ],
        ]);
    }

    public function testThrowsWhenPsr4StrategyHasNoMappings(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        RoutingAttributesConfig::fromRootConfig([
            'routing_attributes' => [
                'classes'   => [],
                'discovery' => [
                    'enabled'  => true,
                    'paths'    => ['/app/src'],
                    'strategy' => 'psr4',
                    'psr4'     => [
                        'mappings' => [],
                    ],
                ],
            ],
        ]);
    }

    public function testThrowsWhenDiscoveryPsr4FallbackTypeInvalid(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        RoutingAttributesConfig::fromRootConfig([
            'routing_attributes' => [
                'classes'   => [],
                'discovery' => [
                    'enabled' => true,
                    'paths'   => ['/app/src'],
                    'psr4'    => [
                        'fallback_to_token' => 'invalid',
                    ],
                ],
            ],
        ]);
    }

    public function testThrowsWhenDiscoveryPsr4MappingPathInvalid(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        RoutingAttributesConfig::fromRootConfig([
            'routing_attributes' => [
                'classes'   => [],
                'discovery' => [
                    'enabled'  => true,
                    'paths'    => ['/app/src'],
                    'strategy' => 'psr4',
                    'psr4'     => [
                        'mappings' => [
                            '' => 'App\\',
                        ],
                    ],
                ],
            ],
        ]);
    }

    public function testThrowsWhenDiscoveryPsr4MappingNamespaceInvalid(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        RoutingAttributesConfig::fromRootConfig([
            'routing_attributes' => [
                'classes'   => [],
                'discovery' => [
                    'enabled'  => true,
                    'paths'    => ['/app/src'],
                    'strategy' => 'psr4',
                    'psr4'     => [
                        'mappings' => [
                            '/app/src' => '',
                        ],
                    ],
                ],
            ],
        ]);
    }

    #[DataProvider('invalidlyTypedConfigurations')]
    public function testRejectsInvalidlyTypedConfigurationKeys(mixed $routingAttributes, string $message): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage($message);

        RoutingAttributesConfig::fromRootConfig([
            'routing_attributes' => $routingAttributes,
        ]);
    }

    /** @return iterable<string, array{mixed, string}> */
    public static function invalidlyTypedConfigurations(): iterable
    {
        yield 'routing_attributes' => ['invalid', '"routing_attributes" must be an array; received string.'];

        yield 'classes' => [[
            'classes' => 'App\Handler',
        ], '"routing_attributes.classes" must be an array; received string.'];

        yield 'class entry' => [[
            'classes' => ['App\Handler', 42],
        ], 'must contain non-empty strings; received int at index "1".'];

        yield 'empty class entry' => [[
            'classes' => [''],
        ], 'must contain non-empty strings; received string at index "0".'];

        yield 'handlers' => [[
            'handlers' => 'psr15',
        ], '"routing_attributes.handlers" must be an array; received string.'];

        yield 'discovery enabled' => [
            [
                'discovery' => [
                    'enabled' => 'yes',
                ],
            ],
            '"routing_attributes.discovery.enabled" must be a boolean; received string.',
        ];

        yield 'discovery paths' => [
            [
                'discovery' => [
                    'enabled' => true,
                    'paths'   => '/app/src',
                ],
            ],
            '"routing_attributes.discovery.paths" must be an array; received string.',
        ];

        yield 'discovery psr4' => [
            [
                'discovery' => [
                    'enabled'  => true,
                    'paths'    => [__DIR__],
                    'strategy' => 'psr4',
                    'psr4'     => 'App\\',
                ],
            ],
            '"routing_attributes.discovery.psr4" must be an array; received string.',
        ];

        yield 'discovery psr4 mappings' => [
            [
                'discovery' => [
                    'enabled'  => true,
                    'paths'    => [__DIR__],
                    'strategy' => 'psr4',
                    'psr4'     => [
                        'mappings' => 'App\\',
                    ],
                ],
            ],
            '"routing_attributes.discovery.psr4.mappings" must be an array; received string.',
        ];
    }
}
