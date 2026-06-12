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

    /**
     * @dataProvider provideIsBetween
     */
    public function testIsBetween(
        DateTimeImmutable $uuidTime,
        DateTimeImmutable $start,
        DateTimeImmutable $end,
        bool $expected,
    ): void {
        /** @var UuidV7 $uuid */
        $uuid = Uuid::uuid7($uuidTime);

        $this->assertSame($expected, $uuid->isBetween($start, $end));
    }

    /**
     * @return iterable<string, array{uuidTime: DateTimeImmutable, start: DateTimeImmutable, end: DateTimeImmutable, expected: bool}>
     */
    public function provideIsBetween(): iterable
    {
        $uuidTime = DateTimeImmutable::createFromFormat('U.u', '1700000000.123456');
        $beforeWindow = DateTimeImmutable::createFromFormat('U.u', '1699999999.000000');
        $justBeforeWindow = DateTimeImmutable::createFromFormat('U.u', '1700000000.122999');
        $exactStart = DateTimeImmutable::createFromFormat('U.u', '1700000000.123000');
        $insideStart = DateTimeImmutable::createFromFormat('U.u', '1700000000.120000');
        $insideEnd = DateTimeImmutable::createFromFormat('U.u', '1700000000.125000');
        $exactEndTruncated = DateTimeImmutable::createFromFormat('U.u', '1700000000.123000');
        $justAfterWindow = DateTimeImmutable::createFromFormat('U.u', '1700000000.124000');
        $afterWindow = DateTimeImmutable::createFromFormat('U.u', '1700000001.000000');

        yield 'uuid falls strictly inside the window' => [
            'uuidTime' => $uuidTime,
            'start' => $insideStart,
            'end' => $insideEnd,
            'expected' => true,
        ];

        yield 'uuid timestamp equals start (inclusive)' => [
            'uuidTime' => $uuidTime,
            'start' => $exactStart,
            'end' => $insideEnd,
            'expected' => true,
        ];

        yield 'uuid timestamp equals end (inclusive)' => [
            'uuidTime' => $uuidTime,
            'start' => $beforeWindow,
            'end' => $exactEndTruncated,
            'expected' => true,
        ];

        yield 'uuid timestamp falls before the window' => [
            'uuidTime' => $uuidTime,
            'start' => $justAfterWindow,
            'end' => $afterWindow,
            'expected' => false,
        ];

        yield 'uuid timestamp falls after the window' => [
            'uuidTime' => $uuidTime,
            'start' => $beforeWindow,
            'end' => $justBeforeWindow,
            'expected' => false,
        ];

        yield 'sub-millisecond precision on boundaries is truncated (123999 us == 123 ms)' => [
            'uuidTime' => $uuidTime,
            'start' => DateTimeImmutable::createFromFormat('U.u', '1700000000.123999'),
            'end' => DateTimeImmutable::createFromFormat('U.u', '1700000000.123999'),
            'expected' => true,
        ];

        yield 'start strictly greater than end throws' => [
            'uuidTime' => $uuidTime,
            'start' => DateTimeImmutable::createFromFormat('U.u', '1700000001.000000'),
            'end' => DateTimeImmutable::createFromFormat('U.u', '1700000000.000000'),
            'expected' => false, // never reached; the exception is asserted separately.
        ];
    }

    public function testIsBetweenThrowsWhenStartIsAfterEnd(): void
    {
        /** @var UuidV7 $uuid */
        $uuid = Uuid::uuid7(DateTimeImmutable::createFromFormat('U.u', '1700000000.123456'));

        $start = DateTimeImmutable::createFromFormat('U.u', '1700000001.000000');
        $end = DateTimeImmutable::createFromFormat('U.u', '1700000000.000000');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('start must be before or equal to end');

        $uuid->isBetween($start, $end);
    }
}
