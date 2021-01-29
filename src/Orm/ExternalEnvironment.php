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

namespace Hector\Orm;

/**
 * Trait ExternalEnvironment.
 *
 * @package Hector\Orm
 */
trait ExternalEnvironment
{
    /** @var mixed External environment */
    private mixed $externalEnvironment = null;

    /**
     * Set external environment.
     *
     * @param mixed $environment
     */
    public function setExternalEnvironment(mixed $environment): void
    {
        $this->externalEnvironment = $environment;
    }

    /**
     * Get external environment.
     *
     * @return mixed
     */
    public function getExternalEnvironment(): mixed
    {
        return $this->externalEnvironment;
    }
}