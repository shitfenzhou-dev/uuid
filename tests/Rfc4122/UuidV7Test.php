<?php

declare(strict_types=1);

namespace Ramsey\Uuid\Test\Rfc4122;

use DateTimeImmutable;
use Mockery;
use Ramsey\Uuid\Codec\CodecInterface;
use Ramsey\Uuid\Converter\NumberConverterInterface;
use Ramsey\Uuid\Converter\TimeConverterInterface;
use Ramsey\Uuid\Exception\DateTimeException;
use Ramsey\Uuid\Exception\InvalidArgumentException;
use Ramsey\Uuid\Rfc4122\FieldsInterface;
use Ramsey\Uuid\Rfc4122\UuidV7;
use Ramsey\Uuid\Test\TestCase;
use Ramsey\Uuid\Type\Hexadecimal;
use Ramsey\Uuid\Type\Time;
use Ramsey\Uuid\Uuid;

class UuidV7Test extends TestCase
{
    /**
     * @dataProvider provideTestVersions
     */
    public function testConstructorThrowsExceptionWhenFieldsAreNotValidForType(int $version): void
    {
        $fields = Mockery::mock(FieldsInterface::class, [
            'getVersion' => $version,
        ]);

        $numberConverter = Mockery::mock(NumberConverterInterface::class);
        $codec = Mockery::mock(CodecInterface::class);
        $timeConverter = Mockery::mock(TimeConverterInterface::class);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Fields used to create a UuidV7 must represent a '
            . 'version 7 (Unix Epoch time) UUID'
        );

        new UuidV7($fields, $numberConverter, $codec, $timeConverter);
    }

    /**
     * @return array<array{version: int}>
     */
    public function provideTestVersions(): array
    {
        return [
            ['version' => 0],
            ['version' => 1],
            ['version' => 2],
            ['version' => 3],
            ['version' => 4],
            ['version' => 5],
            ['version' => 6],
            ['version' => 8],
            ['version' => 9],
        ];
    }

    /**
     * @param non-empty-string $uuid
     *
     * @dataProvider provideUuidV7WithMicroseconds
     */
    public function testGetDateTimeProperlyHandlesMicroseconds(string $uuid, string $expected): void
    {
        /** @var UuidV7 $object */
        $object = Uuid::fromString($uuid);

        $date = $object->getDateTime();

        $this->assertInstanceOf(DateTimeImmutable::class, $date);
        $this->assertSame($expected, $date->format('U.u'));
    }

    /**
     * @return array<array{uuid: string, expected: numeric-string}>
     */
    public function provideUuidV7WithMicroseconds(): array
    {
        return [
            [
                'uuid' => '00000000-0001-71b2-9669-00007ffffffe',
                'expected' => '0.001000',
            ],
            [
                'uuid' => '00000000-000f-71b2-9669-00007ffffffe',
                'expected' => '0.015000',
            ],
            [
                'uuid' => '00000000-0064-71b2-9669-00007ffffffe',
                'expected' => '0.100000',
            ],
            [
                'uuid' => '00000000-03e7-71b2-9669-00007ffffffe',
                'expected' => '0.999000',
            ],
            [
                'uuid' => '00000000-03e8-71b2-9669-00007ffffffe',
                'expected' => '1.000000',
            ],
            [
                'uuid' => '00000000-03e9-71b2-9669-00007ffffffe',
                'expected' => '1.001000',
            ],
        ];
    }

    public function testGetDateTimeThrowsException(): void
    {
        $fields = Mockery::mock(FieldsInterface::class, [
            'getVersion' => 7,
            'getTimestamp' => new Hexadecimal('0'),
        ]);

        $numberConverter = Mockery::mock(NumberConverterInterface::class);
        $codec = Mockery::mock(CodecInterface::class);

        $timeConverter = Mockery::mock(TimeConverterInterface::class, [
            'convertTime' => new Time('0', '1234567'),
        ]);

        $uuid = new UuidV7($fields, $numberConverter, $codec, $timeConverter);

        $this->expectException(DateTimeException::class);

        $uuid->getDateTime();
    }

    public function testIsBetweenReturnsTrueWhenTimestampWithinRange(): void
    {
        $uuid = Uuid::uuid7();
        $uuidTime = DateTimeImmutable::createFromInterface($uuid->getDateTime());

        $start = $uuidTime->modify('-1 second');
        $end = $uuidTime->modify('+1 second');

        $this->assertTrue($uuid->isBetween($start, $end));
    }

    public function testIsBetweenReturnsTrueWhenEqualToStart(): void
    {
        $uuid = Uuid::uuid7();
        $uuidTime = DateTimeImmutable::createFromInterface($uuid->getDateTime());

        $start = $uuidTime;
        $end = $uuidTime->modify('+1 second');

        $this->assertTrue($uuid->isBetween($start, $end));
    }

    public function testIsBetweenReturnsTrueWhenEqualToEnd(): void
    {
        $uuid = Uuid::uuid7();
        $uuidTime = DateTimeImmutable::createFromInterface($uuid->getDateTime());

        $start = $uuidTime->modify('-1 second');
        $end = $uuidTime;

        $this->assertTrue($uuid->isBetween($start, $end));
    }

    public function testIsBetweenReturnsTrueWhenStartEqualsEnd(): void
    {
        $uuid = Uuid::uuid7();
        $uuidTime = DateTimeImmutable::createFromInterface($uuid->getDateTime());

        $this->assertTrue($uuid->isBetween($uuidTime, $uuidTime));
    }

    public function testIsBetweenReturnsFalseWhenBeforeStart(): void
    {
        $uuid = Uuid::uuid7();
        $uuidTime = DateTimeImmutable::createFromInterface($uuid->getDateTime());

        $start = $uuidTime->modify('+1 second');
        $end = $uuidTime->modify('+2 seconds');

        $this->assertFalse($uuid->isBetween($start, $end));
    }

    public function testIsBetweenReturnsFalseWhenAfterEnd(): void
    {
        $uuid = Uuid::uuid7();
        $uuidTime = DateTimeImmutable::createFromInterface($uuid->getDateTime());

        $start = $uuidTime->modify('-2 seconds');
        $end = $uuidTime->modify('-1 second');

        $this->assertFalse($uuid->isBetween($start, $end));
    }

    /**
     * @param non-empty-string $uuid
     * @param non-empty-string $startStr
     * @param non-empty-string $endStr
     *
     * @dataProvider provideIsBetweenWithMicrosecondRounding
     */
    public function testIsBetweenProperlyHandlesMicrosecondRounding(
        string $uuid,
        string $startStr,
        string $endStr,
        bool $expected,
    ): void {
        /** @var UuidV7 $object */
        $object = Uuid::fromString($uuid);

        $start = new DateTimeImmutable('@' . $startStr);
        $end = new DateTimeImmutable('@' . $endStr);

        $this->assertSame($expected, $object->isBetween($start, $end));
    }

    /**
     * @return array<array{uuid: non-empty-string, startStr: non-empty-string, endStr: non-empty-string, expected: bool}>
     */
    public function provideIsBetweenWithMicrosecondRounding(): array
    {
        return [
            [
                'uuid' => '00000000-0001-71b2-9669-00007ffffffe',
                'startStr' => '0.000123',
                'endStr' => '0.001500',
                'expected' => true,
            ],
            [
                'uuid' => '00000000-0001-71b2-9669-00007ffffffe',
                'startStr' => '0.002000',
                'endStr' => '0.003000',
                'expected' => false,
            ],
            [
                'uuid' => '00000000-03e7-71b2-9669-00007ffffffe',
                'startStr' => '0.999000',
                'endStr' => '1.000000',
                'expected' => true,
            ],
            [
                'uuid' => '00000000-03e7-71b2-9669-00007ffffffe',
                'startStr' => '0.998123',
                'endStr' => '0.998999',
                'expected' => false,
            ],
        ];
    }

    public function testIsBetweenThrowsExceptionWhenStartAfterEnd(): void
    {
        $uuid = Uuid::uuid7();
        $uuidTime = DateTimeImmutable::createFromInterface($uuid->getDateTime());

        $start = $uuidTime->modify('+1 second');
        $end = $uuidTime->modify('-1 second');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('start must be before or equal to end');

        $uuid->isBetween($start, $end);
    }
}
