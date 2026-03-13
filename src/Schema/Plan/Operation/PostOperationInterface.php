<?php
/*
 * This file is part of Hector ORM.
 *
 * @license   https://opensource.org/licenses/MIT MIT License
 * @copyright 2026 Ronan GIRON
 * @author    Ronan GIRON <https://github.com/ElGigi>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code, to the root.
 */

declare(strict_types=1);

namespace Hector\Schema\Plan\Operation;

use Hector\Schema\Plan\OperationInterface;

/**
 * Marker interface for operations that must be executed after the main structure pass.
 *
 * Examples: ADD FOREIGN KEY, CREATE VIEW, ALTER VIEW.
 */
interface PostOperationInterface extends OperationInterface
{
}
