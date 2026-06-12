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

use DateTimeInterface;
use Ramsey\Uuid\Codec\CodecInterface;
use Ramsey\Uuid\Converter\NumberConverterInterface;
use Ramsey\Uuid\Converter\TimeConverterInterface;
use Ramsey\Uuid\Exception\InvalidArgumentException;
use Ramsey\Uuid\Rfc4122\FieldsInterface as Rfc4122FieldsInterface;
use Ramsey\Uuid\Uuid;

use function str_pad;

use const STR_PAD_LEFT;

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

    public function isBetween(DateTimeInterface $start, DateTimeInterface $end): bool
    {
        $startTimestamp = $this->dateTimeToTimestamp($start);
        $endTimestamp = $this->dateTimeToTimestamp($end);

        if ($startTimestamp > $endTimestamp) {
            throw new InvalidArgumentException('start must be before or equal to end');
        }

        $uuidTimestamp = str_pad($this->fields->getTimestamp()->toString(), 15, '0', STR_PAD_LEFT);

        return $uuidTimestamp >= $startTimestamp && $uuidTimestamp <= $endTimestamp;
    }

    private function dateTimeToTimestamp(DateTimeInterface $dateTime): string
    {
        return str_pad(
            $this->timeConverter->calculateTime($dateTime->format('U'), $dateTime->format('u'))->toString(),
            15,
            '0',
            STR_PAD_LEFT,
        );
    }
}
