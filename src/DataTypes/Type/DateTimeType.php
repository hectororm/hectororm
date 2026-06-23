<?php
/*
 * This file is part of Hector ORM.
 *
 * @license   https://opensource.org/licenses/MIT MIT License
 * @copyright 2021 Ronan GIRON
 * @author    Ronan GIRON <https://github.com/ElGigi>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code, to the root.
 */

declare(strict_types=1);

namespace Hector\DataTypes\Type;

use DateTime;
use DateTimeInterface;
use DateTimeZone;
use Hector\DataTypes\Exception\ValueException;
use Hector\DataTypes\ExpectedType;
use Throwable;
use ValueError;

class DateTimeType extends AbstractType
{
    public function __construct(
        protected string $format = 'Y-m-d H:i:s',
        protected string $class = DateTime::class,
        protected ?DateTimeZone $timezone = null,
    ) {
        if (false === is_a($this->class, DateTimeInterface::class, true)) {
            throw new ValueError(
                sprintf(
                    'Expected a "%s" interface, "%s" given',
                    DateTimeInterface::class,
                    $this->class
                )
            );
        }
    }

    /**
     * Resolve the timezone applied to every date/time path.
     *
     * When no timezone is configured, the ambient PHP default timezone is used,
     * preserving the previous behaviour of the string path while still letting
     * the numeric/timestamp path agree with it.
     */
    private function resolveTimezone(): DateTimeZone
    {
        return $this->timezone ?? new DateTimeZone(date_default_timezone_get());
    }

    /**
     * @inheritDoc
     */
    public function fromSchema(mixed $value, ?ExpectedType $expected = null): mixed
    {
        if (null === $value) {
            $this->assertNullable($expected);

            return null;
        }

        try {
            $timezone = $this->resolveTimezone();

            if (null === $expected) {
                return new $this->class((string)$value, $timezone);
            }

            if ($expected->getName() == 'string') {
                return (string)$value;
            }

            if ($expected->getName() == 'int') {
                $timestamp = strtotime((string)$value);

                // strtotime() returns false on an invalid date; (int)false would
                // be 0 (1970-01-01), silently corrupting the value.
                if (false === $timestamp) {
                    throw ValueException::castError($this);
                }

                return $timestamp;
            }

            if (is_a($expected->getName(), DateTimeInterface::class, true)) {
                $class = $expected->getName();

                return new $class((string)$value, $timezone);
            }
        } catch (Throwable $e) {
            throw ValueException::castError($this, $e);
        }

        throw ValueException::castError($this);
    }

    /**
     * @inheritDoc
     */
    public function toSchema(mixed $value, ?ExpectedType $expected = null): ?string
    {
        if (null === $value) {
            return null;
        }

        try {
            $timezone = $this->resolveTimezone();

            if (is_numeric($value)) {
                // The '@timestamp' syntax always yields a UTC DateTime; move it to
                // the resolved timezone so it agrees with the string path instead of
                // rendering UTC wall-clock.
                $value = (new DateTime(sprintf('@%d', $value)))->setTimezone($timezone);
            } elseif (is_string($value)) {
                $value = new DateTime($value, $timezone);
            }

            if ($value instanceof DateTimeInterface) {
                return $value->format($this->format);
            }

            throw ValueException::castError($this);
        } catch (ValueException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw ValueException::castError($this, $exception);
        }
    }
}
