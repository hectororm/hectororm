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

namespace Hector\Orm\Attributes;

use Hector\Orm\Assert\EntityAssert;
use Hector\Orm\Exception\OrmException;
use Hector\Orm\Relationship\Relationship;
use Hector\Orm\Relationship\Relationships;

/**
 * Class RelationshipAttribute.
 *
 * @package Hector\Orm\Attributes
 */
abstract class RelationshipAttribute
{
    use EntityAssert;

    public function __construct(
        public string $target,
        public string $name,
        protected array $params = [],
    ) {
        $this->assertEntity($this->target);
    }

    /**
     * Add relationship.
     *
     * @param Relationships $relationships
     *
     * @return Relationship
     * @throws OrmException
     */
    abstract protected function addRelationship(Relationships $relationships): Relationship;

    /**
     * Init relationship.
     *
     * @param Relationships $relationships
     *
     * @return Relationship
     * @throws OrmException
     */
    final public function init(Relationships $relationships): Relationship
    {
        $relationship = $this->addRelationship($relationships);
        $this->addClauses($relationship);

        return $relationship;
    }

    /**
     * Add clauses to relationship.
     *
     * @param Relationship $relationship
     */
    protected function addClauses(Relationship $relationship): void
    {
        if (isset($this->params['where'])) {
            $relationship->whereEquals($this->params['where']);
        }

        if (isset($this->params['groupBy'])) {
            foreach ((array)$this->params['groupBy'] as $value) {
                $relationship->groupBy($value);
            }
        }

        if (isset($this->params['having'])) {
            $relationship->havingEquals($this->params['having']);
        }

        if (isset($this->params['orderBy'])) {
            foreach ((array)$this->params['orderBy'] as $key => $value) {
                if (is_numeric($key)) {
                    $relationship->orderBy($value);
                    continue;
                }

                $relationship->orderBy($key, $value);
            }
        }

        if (isset($this->params['limit'])) {
            $limit = (array)$this->params['limit'];
            $relationship->limit($limit[0] ?? null, $limit[1] ?? null);
        }
    }
}