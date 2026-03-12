<?php

declare(strict_types=1);

namespace SirixTest\Mezzio\Routing\Attributes\Extractor\Fixture;

use Sirix\Mezzio\Routing\Attributes\Attribute\Get;

#[Get('/invalid', name: 'invalid')]
final class NotMiddleware {}
