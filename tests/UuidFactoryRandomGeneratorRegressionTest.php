<?php

declare(strict_types=1);

namespace Ramsey\Uuid\Test;

use DateTimeImmutable;
use Ramsey\Uuid\Generator\RandomGeneratorInterface;
use Ramsey\Uuid\UuidFactory;

use function chr;
use function str_repeat;

class UuidFactoryRandomGeneratorRegressionTest extends TestCase
{
    public function testSetRandomGeneratorUpdatesRandomSourceForUuid4AndUuid7(): void
    {
        $factory = new UuidFactory();
        $generator = new RecordingRandomGenerator();
        $factory->setRandomGenerator($generator);

        $uuid4 = $factory->uuid4();

        $this->assertSame(4, $uuid4->getVersion());
        $this->assertCount(1, $generator->calls);
        $this->assertSame(16, $generator->calls[0]);

        $uuid7 = $factory->uuid7(new DateTimeImmutable('9999-12-31 23:59:59.987000+00:00'));

        $this->assertSame(7, $uuid7->getVersion());
        $this->assertCount(2, $generator->calls);
    }
}

class RecordingRandomGenerator implements RandomGeneratorInterface
{
    public array $calls = [];
    private int $value = 0;

    public function generate(int $length): string
    {
        $this->calls[] = $length;
        $this->value++;

        return str_repeat(chr($this->value), $length);
    }
}
