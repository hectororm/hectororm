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

namespace Hector\Query\Statement;

use Hector\Connection\Bind\BindParamList;
use Hector\Connection\Driver\DriverCapabilities;
use Hector\Query\StatementInterface;

class Encapsulated implements StatementInterface
{
    /**
     * Encapsulated constructor.
     *
     * @param StatementInterface $statement
     */
    public function __construct(
        private StatementInterface $statement,
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getStatement(
        BindParamList $bindParams,
        ?DriverCapabilities $driverCapabilities = null,
    ): ?string {
        $str = $this->statement->getStatement($bindParams, $driverCapabilities);

        if (null === $str || '' === $str) {
            return null;
        }

        return '( ' . $str . ' )';
    }
}
