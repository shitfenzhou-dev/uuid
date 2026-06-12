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

    public function testIsBetweenThrowsExceptionWhenStartIsGreaterThanEnd(): void
    {
        /** @var UuidV7 $uuid */
        $uuid = Uuid::fromString('01851e59-a5e0-71a2-9721-361c77846ba8'); // Just a valid v7 UUID

        $start = new DateTimeImmutable('2023-01-01 10:00:01.000');
        $end = new DateTimeImmutable('2023-01-01 10:00:00.000');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('start must be before or equal to end');

        $uuid->isBetween($start, $end);
    }

    /**
     * @dataProvider provideIsBetweenCases
     */
    public function testIsBetween(
        string $uuidStr,
        string $startStr,
        string $endStr,
        bool $expected
    ): void {
        /** @var UuidV7 $uuid */
        $uuid = Uuid::fromString($uuidStr);

        $start = DateTimeImmutable::createFromFormat('Y-m-d H:i:s.u', $startStr, new \DateTimeZone('UTC'));
        $end = DateTimeImmutable::createFromFormat('Y-m-d H:i:s.u', $endStr, new \DateTimeZone('UTC'));

        // $start and $end shouldn't be false here
        $this->assertInstanceOf(DateTimeImmutable::class, $start);
        $this->assertInstanceOf(DateTimeImmutable::class, $end);

        $this->assertSame($expected, $uuid->isBetween($start, $end));
    }

    /**
     * @return array<string, array{uuidStr: string, startStr: string, endStr: string, expected: bool}>
     */
    public function provideIsBetweenCases(): array
    {
        // 01851e59-a5e0-71a2-9721-361c77846ba8 corresponds to Unix epoch 1671234567890 ms (approx)
        // Let's pick a known UUID v7 and its exact time.
        // uuid = 00000000-0064-71b2-9669-00007ffffffe
        // From provideUuidV7WithMicroseconds(), this is 100 milliseconds since epoch (0.100000).
        // 100 ms is 1970-01-01 00:00:00.100000
        
        $uuid = '00000000-0064-71b2-9669-00007ffffffe';
        
        return [
            'exactly equal to start and end' => [
                'uuidStr' => $uuid,
                'startStr' => '1970-01-01 00:00:00.100000',
                'endStr' => '1970-01-01 00:00:00.100000',
                'expected' => true,
            ],
            'falls inside window' => [
                'uuidStr' => $uuid,
                'startStr' => '1970-01-01 00:00:00.099000',
                'endStr' => '1970-01-01 00:00:00.101000',
                'expected' => true,
            ],
            'exactly equal to start' => [
                'uuidStr' => $uuid,
                'startStr' => '1970-01-01 00:00:00.100000',
                'endStr' => '1970-01-01 00:00:00.105000',
                'expected' => true,
            ],
            'exactly equal to end' => [
                'uuidStr' => $uuid,
                'startStr' => '1970-01-01 00:00:00.090000',
                'endStr' => '1970-01-01 00:00:00.100000',
                'expected' => true,
            ],
            'outside window (before)' => [
                'uuidStr' => $uuid,
                'startStr' => '1970-01-01 00:00:00.101000',
                'endStr' => '1970-01-01 00:00:00.200000',
                'expected' => false,
            ],
            'outside window (after)' => [
                'uuidStr' => $uuid,
                'startStr' => '1970-01-01 00:00:00.050000',
                'endStr' => '1970-01-01 00:00:00.099000',
                'expected' => false,
            ],
            'truncates start microsecond up correctly (123999 -> 123000)' => [
                'uuidStr' => $uuid,
                // The UUID time is .100000. 
                // If start is .100999, it truncates to .100000, so it becomes equal to start.
                'startStr' => '1970-01-01 00:00:00.100999',
                'endStr' => '1970-01-01 00:00:00.200000',
                'expected' => true,
            ],
            'truncates end microsecond correctly (099999 -> 099000)' => [
                'uuidStr' => $uuid,
                // The UUID time is .100000.
                // If end is .099999, it truncates to .099000.
                // So UUID (.100000) is > end (.099000). Expected false.
                'startStr' => '1970-01-01 00:00:00.050000',
                'endStr' => '1970-01-01 00:00:00.099999',
                'expected' => false,
            ],
        ];
    }
}
