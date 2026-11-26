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
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Hector\DataTypes\Exception\ValueException;
use Hector\DataTypes\ExpectedType;
use Throwable;
use ValueError;

class DateTimeType extends AbstractType implements StorageTimezoneAwareInterface
{
    protected ?DateTimeZone $storageTimezone = null;

    /**
     * DateTimeType constructor.
     *
     * @param string $format Format of the stored value
     * @param string $class Class of the returned objects
     * @param DateTimeZone|null $timezone Timezone of the returned objects, and of the stored
     *                                    values unless a storage timezone is given. Null
     *                                    means the PHP default timezone.
     *
     * @see DateTimeType::withStorageTimezone()
     */
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
     * @inheritDoc
     */
    public function withStorageTimezone(DateTimeZone $timezone): static
    {
        $type = clone $this;
        $type->storageTimezone = $timezone;

        return $type;
    }

    /**
     * Resolve the timezone of the returned objects.
     *
     * When no timezone is configured, the ambient PHP default timezone is used.
     */
    private function resolveTimezone(): DateTimeZone
    {
        return $this->timezone ?? new DateTimeZone(date_default_timezone_get());
    }

    /**
     * Resolve the timezone of the stored values, applied to every date/time path.
     *
     * Without a storage timezone, values are stored in the timezone of the
     * returned objects.
     */
    private function resolveStorageTimezone(): DateTimeZone
    {
        return $this->storageTimezone ?? $this->resolveTimezone();
    }

    /**
     * Read a stored value.
     *
     * @param class-string<DateTime|DateTimeImmutable> $class
     */
    private function read(string $class, string $value): DateTime|DateTimeImmutable
    {
        $date = new $class($value, $this->resolveStorageTimezone());

        // Without a storage timezone, the object is already in its timezone.
        if (null === $this->storageTimezone) {
            return $date;
        }

        return $date->setTimezone($this->resolveTimezone());
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
            if (null === $expected) {
                return $this->read($this->class, (string)$value);
            }

            if ($expected->getName() == 'string') {
                return (string)$value;
            }

            if ($expected->getName() == 'int') {
                // An empty value is not a date, but would be read as the current time.
                if ('' === trim((string)$value)) {
                    throw ValueException::castError($this);
                }

                // Read in the storage timezone, like the objects: strtotime() only
                // knows the PHP default timezone. An invalid date throws.
                return (new DateTimeImmutable((string)$value, $this->resolveStorageTimezone()))->getTimestamp();
            }

            if (is_a($expected->getName(), DateTimeInterface::class, true)) {
                return $this->read($expected->getName(), (string)$value);
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
            $timezone = $this->resolveStorageTimezone();

            if (is_numeric($value)) {
                $value = new DateTime(sprintf('@%d', $value));
            } elseif (is_string($value)) {
                $value = new DateTime($value, $timezone);
            }

            if ($value instanceof DateTimeInterface) {
                return DateTimeImmutable::createFromInterface($value)
                    ->setTimezone($timezone)
                    ->format($this->format);
            }

            throw ValueException::castError($this);
        } catch (ValueException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw ValueException::castError($this, $exception);
        }
    }
}
