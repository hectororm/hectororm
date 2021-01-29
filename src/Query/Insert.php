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

namespace Hector\Query;

class Insert implements StatementInterface
{
    use Clause\From;
    use Clause\Assignments;
    use Component\IndentHelperTrait;

    public function __construct()
    {
        $this->reset();
    }

    /**
     * Reset.
     *
     * @return static
     */
    public function reset(): static
    {
        $this
            ->resetFrom()
            ->resetAssignments();

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getStatement(array &$binding, bool $encapsulate = false): ?string
    {
        $fromStr = $this->from->getStatement($binding);
        $assignmentsStr = $this->assignments->getStatement($binding);

        if (null === $fromStr || null === $assignmentsStr) {
            return null;
        }

        $str =
            'INSERT INTO' . PHP_EOL .
            ($this->from->getStatement($binding) ?? '') .
            'SET' . PHP_EOL .
            $assignmentsStr;

        if ($encapsulate) {
            return '(' . PHP_EOL . $this->indent($str) . ')';
        }

        return $str;
    }
}