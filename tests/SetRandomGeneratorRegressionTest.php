<?php

declare(strict_types=1);

namespace Ramsey\Uuid\Test;

use DateTimeImmutable;
use Ramsey\Uuid\Generator\RandomGeneratorInterface;
use Ramsey\Uuid\UuidFactory;

class SetRandomGeneratorRegressionTest extends TestCase
{
    public function testSetRandomGeneratorAffectsUuid4(): void
    {
        $factory = new UuidFactory();
        $spy = new SpyRandomGenerator();

        $factory->setRandomGenerator($spy);

        $factory->uuid4();

        $this->assertGreaterThan(0, $spy->callCount);
        $this->assertSame(4, $factory->uuid4()->getVersion());
    }

    public function testSetRandomGeneratorAffectsUuid7(): void
    {
        $factory = new UuidFactory();
        $spy = new SpyRandomGenerator();

        $factory->setRandomGenerator($spy);

        $dateTime = new DateTimeImmutable('2025-06-12 12:00:00.000');
        $factory->uuid7($dateTime);

        $this->assertGreaterThan(0, $spy->callCount);
        $this->assertSame(7, $factory->uuid7($dateTime)->getVersion());
    }
}

final class SpyRandomGenerator implements RandomGeneratorInterface
{
    public int $callCount = 0;

    public function generate(int $length): string
    {
        $this->callCount++;

        return random_bytes($length);
    }
}
