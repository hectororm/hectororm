<?php

declare(strict_types=1);

namespace Hector\Query\Component;

trait EncapsulateHelperTrait
{
    /**
     * Encapsulate.
     *
     * @param string|null $str
     * @param bool $encapsulate
     *
     * @return string|null
     */
    public function encapsulate(?string $str, bool $encapsulate = true): ?string
    {
        if (empty($str)) {
            return null;
        }

        if (false === $encapsulate) {
            return $str;
        }

        return '( ' . $str . ' )';
    }
}
