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

namespace Hector\Query\Statement;

use Hector\Connection\Bind\BindParamList;
use Hector\Connection\Driver\DriverInfo;
use Hector\Query\StatementInterface;

class Row implements StatementInterface
{
    private array $values;

    /**
     * Row constructor.
     *
     * @param StatementInterface|string ...$value
     */
    public function __construct(StatementInterface|string ...$value)
    {
        $this->values = $value;
    }

    /**
     * @inheritDoc
     */
    public function getStatement(
        BindParamList $bindParams,
        ?DriverInfo $driverInfo = null,
    ): ?string {
        $parts = array_map(
            function (StatementInterface|string $value) use ($bindParams, $driverInfo): string {
                if ($value instanceof StatementInterface) {
                    return $value->getStatement($bindParams, $driverInfo) ?? '';
                }

                return $value;
            },
            $this->values
        );

        return '( ' . implode(', ', $parts) . ' )';
    }
}
