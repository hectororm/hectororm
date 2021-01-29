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

/**
 * Trait IndentHelperTrait.
 *
 * @package Hector\Query\Component
 */
trait IndentHelperTrait
{
    /**
     * Indent.
     *
     * @param string $str
     * @param int $indentation
     *
     * @return string
     */
    protected function indent(string $str = '', int $indentation = 4): string
    {
        return preg_replace('/^/m', str_repeat(' ', $indentation), $str);
    }
}