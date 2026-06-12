<?php

/**
 * This file is part of the ramsey/uuid library
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @copyright Copyright (c) Ben Ramsey <ben@benramsey.com>
 * @license http://opensource.org/licenses/MIT MIT
 */

declare(strict_types=1);

namespace Ramsey\Uuid\Rfc4122;

use Ramsey\Uuid\Codec\CodecInterface;
use Ramsey\Uuid\Converter\NumberConverterInterface;
use Ramsey\Uuid\Converter\TimeConverterInterface;
use Ramsey\Uuid\Exception\InvalidArgumentException;
use Ramsey\Uuid\Rfc4122\FieldsInterface as Rfc4122FieldsInterface;
use Ramsey\Uuid\Uuid;

/**
 * Unix Epoch time, or version 7, UUIDs include a timestamp in milliseconds since the Unix Epoch, along with random bytes
 *
 * @link https://www.rfc-editor.org/rfc/rfc9562#section-5.7 RFC 9562, 5.7. UUID Version 7
 *
 * @immutable
 */
final class UuidV7 extends Uuid implements UuidInterface
{
    use TimeTrait;

    /**
     * Creates a version 7 (Unix Epoch time) UUID
     *
     * @param Rfc4122FieldsInterface $fields The fields from which to construct a UUID
     * @param NumberConverterInterface $numberConverter The number converter to use for converting hex values to/from integers
     * @param CodecInterface $codec The codec to use when encoding or decoding UUID strings
     * @param TimeConverterInterface $timeConverter The time converter to use for converting timestamps extracted from a
     *     UUID to unix timestamps
     */
    public function __construct(
        Rfc4122FieldsInterface $fields,
        NumberConverterInterface $numberConverter,
        CodecInterface $codec,
        TimeConverterInterface $timeConverter,
    ) {
        if ($fields->getVersion() !== Uuid::UUID_TYPE_UNIX_TIME) {
            throw new InvalidArgumentException(
                'Fields used to create a UuidV7 must represent a version 7 (Unix Epoch time) UUID',
            );
        }

        parent::__construct($fields, $numberConverter, $codec, $timeConverter);
    }

    /**
     * Returns true if the timestamp encoded in this version 7 UUID falls within the given (inclusive) time window
     *
     * The comparison is performed at millisecond granularity, matching the 48-bit millisecond Unix Epoch timestamp
     * stored inside a version 7 UUID. Any sub-millisecond precision encoded in `$start` or `$end` is truncated to the
     * millisecond.
     *
     * @param DateTimeInterface $start The beginning of the time window (inclusive)
     * @param DateTimeInterface $end The end of the time window (inclusive)
     *
     * @return bool True if the UUID's timestamp is greater than or equal to `$start` and less than or equal to `$end`
     *
     * @throws InvalidArgumentException if `$start` is after `$end`
     */
    public function isBetween(DateTimeInterface $start, DateTimeInterface $end): bool
    {
        if ($start > $end) {
            throw new InvalidArgumentException('start must be before or equal to end');
        }

        $uuidMs = $this->toMilliseconds($this->fields->getTimestamp());
        $startMs = $this->dateTimeToMilliseconds($start);
        $endMs = $this->dateTimeToMilliseconds($end);

        return $uuidMs >= $startMs && $uuidMs <= $endMs;
    }

    /**
     * Converts the hexadecimal timestamp stored in a version 7 UUID to a signed 64-bit integer of milliseconds since
     * the Unix Epoch.
     *
     * For version 7 UUIDs the timestamp is a 48-bit value of milliseconds since the Unix Epoch. We rely on the
     * {@see TimeConverterInterface} configured on this UUID (typically {@see \Ramsey\Uuid\Converter\Time\UnixTimeConverter})
     * to convert the hexadecimal timestamp back to a (seconds, microseconds) pair that we can recombine into a single
     * millisecond value.
     */
    private function toMilliseconds(Hexadecimal $uuidTimestamp): int
    {
        $time = $this->timeConverter->convertTime($uuidTimestamp);

        $seconds = (int) $time->getSeconds()->toString();
        $microseconds = (int) $time->getMicroseconds()->toString();

        return $seconds * 1000 + (int) ($microseconds / 1000);
    }

    /**
     * Converts a `DateTimeInterface` instance to an integer number of milliseconds since the Unix Epoch, truncating
     * any sub-millisecond precision.
     *
     * The `u` format specifier always returns a six-digit string (0-padded on the left) representing microseconds,
     * so `123456 / 1000 = 123 ms` and `123999 / 1000 = 123 ms` (integer division discards the remainder).
     */
    private function dateTimeToMilliseconds(DateTimeInterface $dateTime): int
    {
        $seconds = (int) $dateTime->format('U');
        $microseconds = (int) $dateTime->format('u');

        return $seconds * 1000 + (int) ($microseconds / 1000);
    }
}
