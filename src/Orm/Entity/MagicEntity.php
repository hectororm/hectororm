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

namespace Hector\Orm\Entity;

use Hector\Orm\Attributes;
use Hector\Orm\Exception\OrmException;
use Hector\Orm\Mapper\MagicMapper;
use JsonSerializable;

#[Attributes\Mapper(MagicMapper::class)]
abstract class MagicEntity extends Entity implements JsonSerializable
{
    protected array $_hectorAttributes = [];

    public function __serialize(): array
    {
        return [
            '_hectorAttributes' => $this->_hectorAttributes,
            'parent' => parent::__serialize(),
        ];
    }

    public function __unserialize(array $data): void
    {
        $this->_hectorAttributes = $data['_hectorAttributes'];
        parent::__unserialize($data['parent'] ?? []);
    }

    /**
     * @inheritDoc
     */
    public function jsonSerialize(): array
    {
        return $this->_hectorAttributes;
    }

    /**
     * PHP magic method.
     *
     * @return array
     */
    public function __debugInfo(): array
    {
        try {
            return $this->_hectorAttributes + $this->getRelated()->__debugInfo();
        } catch (OrmException) {
            return $this->_hectorAttributes;
        }
    }

    /////////////////////////
    /// GETTERS & SETTERS ///
    /////////////////////////

    /**
     * __isset() PHP method.
     *
     * @param string $name
     *
     * @return bool
     * @throws OrmException
     */
    public function __isset(string $name): bool
    {
        $entityReflection = ReflectionEntity::get($this::class);

        if (in_array($name, $entityReflection->hidden)) {
            return false;
        }

        if (array_key_exists($name, $this->_hectorAttributes)) {
            return true;
        }

        if ($entityReflection->getTable()->hasColumn($name)) {
            return true;
        }

        if ($this->getRelated()->exists($name)) {
            return true;
        }

        return false;
    }

    /**
     * __get() PHP method.
     *
     * @param string $name
     *
     * @return mixed
     * @throws OrmException
     */
    public function __get(string $name): mixed
    {
        // Relationship
        if ($this->getRelated()->exists($name)) {
            return $this->getRelated()->get($name);
        }

        // Property
        if ($this->__isset($name)) {
            return $this->_hectorAttributes[$name] ?? null;
        }

        throw new OrmException(sprintf('Property "%s" not found for "%s" entity', $name, get_class($this)));
    }

    /**
     * __set() PHP method.
     *
     * @param string $name
     * @param mixed $value
     *
     * @throws OrmException
     */
    public function __set(string $name, mixed $value): void
    {
        if ($this->getRelated()->exists($name)) {
            $this->getRelated()->set($name, $value);
            return;
        }

        if ($this->__isset($name)) {
            $this->_hectorAttributes[$name] = $value;
            return;
        }

        throw new OrmException(sprintf('Property "%s" not found for "%s" entity', $name, get_class($this)));
    }
}