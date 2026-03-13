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
use Hector\Connection\Driver\DriverInfo;
use Hector\Query\StatementInterface;

class Expression implements StatementInterface
{
    /** @var array<StatementInterface|string> */
    private array $parts;

    /**
     * Expression constructor.
     *
     * @param StatementInterface|string ...$parts SQL fragments to concatenate at render time
     */
    public function __construct(StatementInterface|string ...$parts)
    {
        $this->parts = $parts;
    }

    /**
     * @inheritDoc
     */
    public function getStatement(
        BindParamList $bindParams,
        ?DriverInfo $driverInfo = null,
    ): ?string {
        $result = '';

        foreach ($this->parts as $part) {
            if ($part instanceof StatementInterface) {
                $resolved = $part->getStatement($bindParams, $driverInfo);

                if (null === $resolved) {
                    return null;
                }

                $result .= $resolved;
            } else {
                $result .= $part;
            }
        }

        return $result ?: null;
    }
}
