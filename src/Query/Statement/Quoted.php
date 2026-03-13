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
use Hector\Query\Helper;
use Hector\Query\StatementInterface;

class Quoted implements StatementInterface
{
    /**
     * Quoted constructor.
     *
     * @param string $identifier SQL identifier, possibly composite (e.g. "schema.table.column")
     */
    public function __construct(
        private string $identifier,
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getStatement(
        BindParamList $bindParams,
        ?DriverInfo $driverInfo = null,
    ): ?string {
        $quote = $driverInfo?->getIdentifierQuote() ?? '`';

        $parts = explode('.', $this->identifier);

        // Preserve wildcard as last segment
        $last = array_pop($parts);
        $hasWildcard = '*' === $last;

        if (false === $hasWildcard) {
            $parts[] = $last;
        }

        $quoted = array_map(
            function (string $part) use ($quote): string {
                $trimmed = trim($part, " \t\n\r\0\x0B`\"");

                return Helper::quote($trimmed ?: null, $quote) ?? '';
            },
            $parts
        );

        if (true === $hasWildcard) {
            $quoted[] = '*';
        }

        return implode('.', $quoted);
    }
}
