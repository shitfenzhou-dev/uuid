<?php

declare(strict_types=1);

namespace Ramsey\Uuid\Test;

use DateTimeImmutable;
use Ramsey\Uuid\Generator\RandomGeneratorInterface;
use Ramsey\Uuid\Rfc4122\UuidV4;
use Ramsey\Uuid\Rfc4122\UuidV7;
use Ramsey\Uuid\UuidFactory;

class UuidFactorySetRandomGeneratorRegressionTest extends TestCase
{
    public function testSetRandomGeneratorPropagatesToUuid4AndUuid7(): void
    {
        $factory = new UuidFactory();

        $callCount = 0;
        $custom = new class ($callCount) implements RandomGeneratorInterface {
            public function __construct(private int &$callCount)
            {
            }

            public function generate(int $length): string
            {
                $this->callCount++;

                return str_repeat("\xff", $length);
            }
        };

        $factory->setRandomGenerator($custom);

        $uuid4 = $factory->uuid4();
        $this->assertGreaterThan(0, $callCount, 'Custom RandomGeneratorInterface::generate() must be called by uuid4() after setRandomGenerator()');

        $uuid7 = $factory->uuid7(new DateTimeImmutable('2024-01-01T00:00:00Z'));
        $this->assertGreaterThan(1, $callCount, 'Custom RandomGeneratorInterface::generate() must be called by uuid7() after setRandomGenerator()');

        $this->assertInstanceOf(UuidV4::class, $uuid4, 'uuid4() must produce a version 4 UUID');
        $this->assertInstanceOf(UuidV7::class, $uuid7, 'uuid7() must produce a version 7 UUID');
    }
}
