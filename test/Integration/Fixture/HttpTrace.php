<?php

declare(strict_types=1);

namespace SirixTest\Mezzio\Routing\Attributes\Integration\Fixture;

final class HttpTrace
{
    /** @var list<string> */
    public array $events = [];

    /** @var array<string, int> */
    public array $created = [];
}
