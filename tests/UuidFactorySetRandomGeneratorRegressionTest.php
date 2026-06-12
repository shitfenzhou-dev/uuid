<?php

declare(strict_types=1);

namespace Ramsey\Uuid\Test;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Generator\RandomGeneratorInterface;
use Ramsey\Uuid\UuidFactory;

class UuidFactorySetRandomGeneratorRegressionTest extends TestCase
{
    public function testSetRandomGeneratorUpdatesUuid4AndUuid7(): void
    {
        $factory = new UuidFactory();

        $customGenerator = new class implements RandomGeneratorInterface {
            /** @var array<int, int> */
            public array $callLog = [];

            public function generate(int $length): string
            {
                $this->callLog[] = $length;
                return random_bytes($length);
            }
        };

        $factory->setRandomGenerator($customGenerator);

        // Test uuid4
        $this->assertCount(0, $customGenerator->callLog);
        $uuid4 = $factory->uuid4();
        $this->assertCount(1, $customGenerator->callLog, 'Custom generator should be called once for uuid4');
        $this->assertSame(4, $uuid4->getFields()->getVersion());

        // Test uuid7 with fixed DateTime
        $dateTime = new DateTimeImmutable('2023-01-01 12:00:00.000');
        $uuid7 = $factory->uuid7($dateTime);
        
        $this->assertCount(2, $customGenerator->callLog, 'Custom generator should be called again for uuid7');
        $this->assertSame(7, $uuid7->getFields()->getVersion());
    }
}
