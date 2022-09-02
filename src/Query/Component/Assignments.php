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

namespace Hector\Query\Component;

use Hector\Connection\Bind\BindParamList;
use Hector\Query\StatementInterface;

class Assignments extends AbstractComponent
{
    private array $assignments = [];

    /**
     * Assignment.
     *
     * @param StatementInterface|string $column
     * @param mixed $value
     * @param int|null $type PDO::PARAM_*
     */
    public function assignment(StatementInterface|string $column, mixed $value, ?int $type = null): void
    {
        $this->assignments[] = [
            'column' => $column,
            'value' => $value,
            'type' => $type,
        ];
    }

    /**
     * Assignments.
     *
     * @param array $values
     */
    public function assignments(array $values): void
    {
        foreach ($values as $column => $value) {
            if (is_int($column)) {
                $this->assignments[] = ['column' => $value];
                continue;
            }

            $this->assignment($column, $value);
        }
    }

    /**
     * @inheritDoc
     */
    public function getStatement(BindParamList $bindParams, bool $encapsulate = false): ?string
    {
        return $this->encapsulate(
            implode(
                ', ',
                array_map(
                    function ($assignment) use (&$bindParams) {
                        if (!array_key_exists('value', $assignment)) {
                            return $this->getSubStatement($assignment['column'], $bindParams);
                        }

                        return sprintf(
                            '%s = %s',
                            $this->getSubStatement($assignment['column'], $bindParams),
                            $this->getSubStatementValue($assignment['value'], $bindParams)
                        );
                    },
                    $this->assignments
                )
            ),
            $encapsulate
        );
    }
}