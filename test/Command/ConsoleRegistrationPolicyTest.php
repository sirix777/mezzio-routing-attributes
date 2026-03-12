<?php

declare(strict_types=1);

namespace SirixTest\Mezzio\Routing\Attributes\Command;

use PHPUnit\Framework\TestCase;
use Sirix\Mezzio\Routing\Attributes\Command\ConsoleRegistrationPolicy;

final class ConsoleRegistrationPolicyTest extends TestCase
{
    public function testNoConsoleConfigWhenLaminasCliUnavailable(): void
    {
        $policy = new ConsoleRegistrationPolicy(false, false);

        self::assertFalse($policy->canRegisterConsoleConfig());
        self::assertFalse($policy->shouldRegisterToolingDelegator());
        self::assertFalse($policy->shouldRegisterMezzioRoutesListAlias());
    }

    public function testRegistersAliasWhenOnlyLaminasCliAvailable(): void
    {
        $policy = new ConsoleRegistrationPolicy(true, false);

        self::assertTrue($policy->canRegisterConsoleConfig());
        self::assertFalse($policy->shouldRegisterToolingDelegator());
        self::assertTrue($policy->shouldRegisterMezzioRoutesListAlias());
    }

    public function testRegistersToolingDelegatorWhenAvailable(): void
    {
        $policy = new ConsoleRegistrationPolicy(true, true);

        self::assertTrue($policy->canRegisterConsoleConfig());
        self::assertTrue($policy->shouldRegisterToolingDelegator());
        self::assertFalse($policy->shouldRegisterMezzioRoutesListAlias());
    }
}
