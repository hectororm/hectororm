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

namespace Hector\Schema\Generator;

use Hector\Schema\Exception\SchemaException;
use Hector\Schema\Schema;
use Hector\Schema\SchemaContainer;

/**
 * Interface GeneratorInterface.
 *
 * @package Hector\Schema\Generator
 */
interface GeneratorInterface
{
    /**
     * Generate schema.
     *
     * @param string $name
     *
     * @return Schema
     * @throws SchemaException
     */
    public function generateSchema(string $name): Schema;

    /**
     * Generate schemas.
     *
     * @param string ...$names
     *
     * @return SchemaContainer
     * @throws SchemaException
     */
    public function generateSchemas(string ...$names): SchemaContainer;
}