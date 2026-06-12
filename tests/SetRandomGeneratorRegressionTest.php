<?php

declare(strict_types=1);

namespace Ramsey\Uuid\Test;

use DateTimeImmutable;
use Ramsey\Uuid\Generator\RandomGeneratorInterface;
use Ramsey\Uuid\UuidFactory;

class SetRandomGeneratorRegressionTest extends TestCase
{
    /**
     * Ensures that after setRandomGenerator(), both uuid4() and uuid7() use the
     * new RandomGeneratorInterface rather than a previously-cached one.
     */
    public function testSetRandomGeneratorPropagatesToUuid4AndUuid7(): void
    {
        $calls = [];

        $randomGenerator = new class ($calls) implements RandomGeneratorInterface {
            /** @var list<array{0: int}> */
            private array $calls;

            public function __construct(array &$calls)
            {
                $this->calls = &$calls;
            }

            public function generate(int $length): string
            {
                $this->calls[] = [$length];

                return random_bytes($length);
            }
        };

        $factory = new UuidFactory();
        $factory->setRandomGenerator($randomGenerator);

        $calls = [];
        $uuid4 = $factory->uuid4();
        $this->assertSame(4, $uuid4->getVersion());
        $this->assertCount(1, $calls);
        $this->assertSame(16, $calls[0][0]);

        $calls = [];
        $dateTime = new DateTimeImmutable('2024-06-01 12:00:00.123');
        $uuid7 = $factory->uuid7($dateTime);
        $this->assertSame(7, $uuid7->getVersion());
        $this->assertNotEmpty($calls);
    }
}