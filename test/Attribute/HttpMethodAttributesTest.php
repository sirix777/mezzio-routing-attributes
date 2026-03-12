<?php

declare(strict_types=1);

namespace SirixTest\Mezzio\Routing\Attributes\Attribute;

use PHPUnit\Framework\TestCase;
use Sirix\Mezzio\Routing\Attributes\Attribute\Any;
use Sirix\Mezzio\Routing\Attributes\Attribute\Delete;
use Sirix\Mezzio\Routing\Attributes\Attribute\Get;
use Sirix\Mezzio\Routing\Attributes\Attribute\Patch;
use Sirix\Mezzio\Routing\Attributes\Attribute\Post;
use Sirix\Mezzio\Routing\Attributes\Attribute\Put;

class HttpMethodAttributesTest extends TestCase
{
    public function testGetAttribute(): void
    {
        $attribute = new Get('/health', 'health', [Post::class]);

        self::assertSame('/health', $attribute->path);
        self::assertSame(['GET'], $attribute->methods);
        self::assertSame('health', $attribute->name);
        self::assertSame([Post::class], $attribute->middleware);
    }

    public function testOtherHttpMethodAttributes(): void
    {
        self::assertSame(['POST'], (new Post('/path'))->methods);
        self::assertSame(['PUT'], (new Put('/path'))->methods);
        self::assertSame(['PATCH'], (new Patch('/path'))->methods);
        self::assertSame(['DELETE'], (new Delete('/path'))->methods);
        self::assertNull((new Any('/path'))->methods);
    }
}
