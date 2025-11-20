<?php
/*
 * This file is part of Hector ORM.
 *
 * @license   https://opensource.org/licenses/MIT MIT License
 * @copyright 2023 Ronan GIRON
 * @author    Ronan GIRON <https://github.com/ElGigi>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code, to the root.
 */

declare(strict_types=1);

namespace Hector\Orm\Query\Component;

use Closure;
use Hector\Orm\Entity\ReflectionEntity;
use Hector\Orm\Orm;
use Hector\Orm\Query\Builder;
use Hector\Query\Helper;
use Hector\Query\StatementInterface;

class Conditions extends \Hector\Query\Component\Conditions
{
    public function __construct(
        Builder $builder,
        private ReflectionEntity $entityReflection,
        private ?\Hector\Orm\Query\Statement\Conditions $conditions = null,
    ) {
        parent::__construct($builder);
    }

    public function add(
        Closure|string|StatementInterface $column,
        ?string $operator = null,
        mixed $value = null,
        string $link = \Hector\Query\Component\Conditions::LINK_AND
    ): void {
        if (is_string($column) && $this->isConditionOnRelationship($column)) {
            $columns = explode('.', $column);
            $columns = array_map(
                fn(string $name): ?string => Helper::trim($name),
                $columns
            );
            $depth = count($columns) - 1;
            $alias = implode('.', array_slice($columns, 0, $depth));

            // Relation already join
            if (true === $this->builder->join->hasAlias($alias)) {
                parent::add(
                    sprintf('%s.%s', Helper::quote($alias), Helper::quote(end($columns))),
                    $operator,
                    $value,
                    $link,
                );
                return;
            }

            // Not a relation
            if (!$this->entityReflection->getMapper()->getRelationships()->exists($columns[0])) {
                parent::add($column, $operator, $value, $link);
                return;
            }

            $i = 0;
            $entityClass = $this->entityReflection->class;
            $alias = Builder::FROM_ALIAS;
            do {
                $mapper = Orm::get()->getMapper($entityClass);
                $relationship = $mapper->getRelationships()->get($columns[$i]);
                $alias = $relationship->addJoinToBuilder(
                    $this->builder,
                    implode('.', array_slice($columns, 0, $i + 1)),
                    $alias
                );
                $entityClass = $relationship->getTargetEntity();
                $i++;
            } while ($i < $depth);

            $relationship->addConditionToBuilder(
                $this->conditions ?? $this->builder,
                $alias,
                $link,
                end($columns),
                $operator,
                $value
            );

            $this->builder->distinct(true);
            return;
        }


        parent::add($column, $operator, $value, $link);
    }

    /**
     * Is condition on relationship?
     *
     * @param $condition
     *
     * @return bool
     */
    private function isConditionOnRelationship($condition): bool
    {
        if (!is_string($condition)) {
            return false;
        }

        return preg_match('/^(\w+\.)+[\w`]+$/i', $condition) === 1;
    }

    /**
     * @inheritDoc
     */
    protected function getClosureArgs(): array
    {
        return [
            new \Hector\Orm\Query\Statement\Conditions($this->builder, $this->entityReflection),
        ];
    }
}
