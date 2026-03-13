<?php

declare(strict_types=1);

namespace SirixTest\Mezzio\Routing\Attributes\Config;

use PHPUnit\Framework\TestCase;
use Sirix\Mezzio\Routing\Attributes\Config\RoutingAttributesConfig;
use Sirix\Mezzio\Routing\Attributes\Exception\InvalidConfigurationException;

final class RoutingAttributesConfigTest extends TestCase
{
    public function testParsesValidConfiguration(): void
    {
        $config = RoutingAttributesConfig::fromRootConfig([
            'routing_attributes' => [
                'classes' => ['App\Handler\PingHandler'],
                'duplicate_strategy' => 'ignore',
                'cache' => [
                    'enabled' => true,
                    'file' => '/tmp/routes.php',
                    'strict' => true,
                    'write_fail_strategy' => 'throw',
                ],
                'discovery' => [
                    'enabled' => true,
                    'paths' => ['/app/src/Handler'],
                    'class_map_cache' => [
                        'enabled' => true,
                        'file' => '/tmp/discovery.php',
                        'validate' => true,
                        'write_fail_strategy' => 'throw',
                    ],
                ],
            ],
        ]);

        self::assertSame(['App\Handler\PingHandler'], $config->classes);
        self::assertSame('ignore', $config->duplicateStrategy);
        self::assertSame('psr15', $config->handlersMode);
        self::assertSame('upstream', $config->classicRoutesMiddlewareDisplay);
        self::assertTrue($config->cacheEnabled);
        self::assertSame('/tmp/routes.php', $config->cacheFile);
        self::assertTrue($config->cacheStrict);
        self::assertSame('throw', $config->cacheWriteFailStrategy);
        self::assertTrue($config->discoveryEnabled);
        self::assertSame(['/app/src/Handler'], $config->discoveryPaths);
        self::assertSame('token', $config->discoveryStrategy);
        self::assertSame([], $config->discoveryPsr4Mappings);
        self::assertTrue($config->discoveryPsr4FallbackToToken);
        self::assertTrue($config->discoveryClassMapCacheEnabled);
        self::assertSame('/tmp/discovery.php', $config->discoveryClassMapCacheFile);
        self::assertTrue($config->discoveryClassMapCacheValidate);
        self::assertSame('throw', $config->discoveryClassMapCacheWriteFailStrategy);
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
        self::assertSame('upstream', $config->classicRoutesMiddlewareDisplay);
        self::assertFalse($config->cacheEnabled);
        self::assertNull($config->cacheFile);
        self::assertFalse($config->cacheStrict);
        self::assertSame('ignore', $config->cacheWriteFailStrategy);
        self::assertFalse($config->discoveryEnabled);
        self::assertSame([], $config->discoveryPaths);
        self::assertSame('token', $config->discoveryStrategy);
        self::assertSame([], $config->discoveryPsr4Mappings);
        self::assertTrue($config->discoveryPsr4FallbackToToken);
        self::assertTrue($config->discoveryClassMapCacheEnabled);
        self::assertNull($config->discoveryClassMapCacheFile);
        self::assertTrue($config->discoveryClassMapCacheValidate);
        self::assertSame('ignore', $config->discoveryClassMapCacheWriteFailStrategy);
    }

    public function testThrowsWhenRootConfigIsInvalid(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        RoutingAttributesConfig::fromRootConfig('invalid');
    }

    public function testThrowsWhenCacheWriteFailStrategyInvalid(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        RoutingAttributesConfig::fromRootConfig([
            'routing_attributes' => [
                'classes' => [],
                'cache' => [
                    'enabled' => true,
                    'file' => '/tmp/routes.php',
                    'write_fail_strategy' => 'invalid',
                ],
            ],
        ]);
    }

    public function testThrowsWhenDiscoveryWriteFailStrategyInvalid(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        RoutingAttributesConfig::fromRootConfig([
            'routing_attributes' => [
                'classes' => [],
                'discovery' => [
                    'enabled' => true,
                    'paths' => ['/app/src/Handler'],
                    'class_map_cache' => [
                        'enabled' => true,
                        'file' => '/tmp/discovery.php',
                        'write_fail_strategy' => 'invalid',
                    ],
                ],
            ],
        ]);
    }

    public function testParsesCallableHandlersMode(): void
    {
        $config = RoutingAttributesConfig::fromRootConfig([
            'routing_attributes' => [
                'classes' => [],
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
                'classes' => [],
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
                'classes' => [],
                'route_list' => [
                    'classic_routes_middleware_display' => 'resolved',
                ],
            ],
        ]);

        self::assertSame('resolved', $config->classicRoutesMiddlewareDisplay);
    }

    public function testThrowsWhenRouteListTypeIsInvalid(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        RoutingAttributesConfig::fromRootConfig([
            'routing_attributes' => [
                'classes' => [],
                'route_list' => 'invalid',
            ],
        ]);
    }

    public function testThrowsWhenClassicRoutesMiddlewareDisplayModeIsInvalid(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        RoutingAttributesConfig::fromRootConfig([
            'routing_attributes' => [
                'classes' => [],
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
                'cache' => [
                    'enabled' => false,
                    'strict' => 'invalid-but-ignored',
                    'write_fail_strategy' => 'invalid-but-ignored',
                ],
            ],
        ]);

        self::assertFalse($config->cacheEnabled);
        self::assertFalse($config->cacheStrict);
        self::assertSame('ignore', $config->cacheWriteFailStrategy);
    }

    public function testDoesNotValidateInactiveDiscoverySubOptions(): void
    {
        $config = RoutingAttributesConfig::fromRootConfig([
            'routing_attributes' => [
                'classes' => [],
                'discovery' => [
                    'enabled' => false,
                    'paths' => 'invalid-but-ignored',
                    'class_map_cache' => [
                        'enabled' => true,
                        'file' => null,
                        'validate' => 'invalid-but-ignored',
                        'write_fail_strategy' => 'invalid-but-ignored',
                    ],
                ],
            ],
        ]);

        self::assertFalse($config->discoveryEnabled);
        self::assertSame([], $config->discoveryPaths);
        self::assertSame('token', $config->discoveryStrategy);
        self::assertSame([], $config->discoveryPsr4Mappings);
        self::assertTrue($config->discoveryPsr4FallbackToToken);
        self::assertTrue($config->discoveryClassMapCacheEnabled);
        self::assertNull($config->discoveryClassMapCacheFile);
        self::assertTrue($config->discoveryClassMapCacheValidate);
        self::assertSame('ignore', $config->discoveryClassMapCacheWriteFailStrategy);
    }

    public function testParsesPsr4DiscoveryConfiguration(): void
    {
        $config = RoutingAttributesConfig::fromRootConfig([
            'routing_attributes' => [
                'classes' => [],
                'discovery' => [
                    'enabled' => true,
                    'paths' => ['/app/src'],
                    'strategy' => 'psr4',
                    'psr4' => [
                        'mappings' => [
                            '/app/src' => 'App\\',
                        ],
                        'fallback_to_token' => false,
                    ],
                    'class_map_cache' => [
                        'enabled' => false,
                    ],
                ],
            ],
        ]);

        self::assertTrue($config->discoveryEnabled);
        self::assertSame('psr4', $config->discoveryStrategy);
        self::assertSame(['/app/src' => 'App\\'], $config->discoveryPsr4Mappings);
        self::assertFalse($config->discoveryPsr4FallbackToToken);
    }

    public function testThrowsWhenDiscoveryStrategyInvalid(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        RoutingAttributesConfig::fromRootConfig([
            'routing_attributes' => [
                'classes' => [],
                'discovery' => [
                    'enabled' => true,
                    'paths' => ['/app/src'],
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
                'classes' => [],
                'discovery' => [
                    'enabled' => true,
                    'paths' => ['/app/src'],
                    'strategy' => 'psr4',
                    'psr4' => [
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
                'classes' => [],
                'discovery' => [
                    'enabled' => true,
                    'paths' => ['/app/src'],
                    'psr4' => [
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
                'classes' => [],
                'discovery' => [
                    'enabled' => true,
                    'paths' => ['/app/src'],
                    'strategy' => 'psr4',
                    'psr4' => [
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
                'classes' => [],
                'discovery' => [
                    'enabled' => true,
                    'paths' => ['/app/src'],
                    'strategy' => 'psr4',
                    'psr4' => [
                        'mappings' => [
                            '/app/src' => '',
                        ],
                    ],
                ],
            ],
        ]);
    }
}
