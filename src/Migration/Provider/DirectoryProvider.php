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

namespace Hector\Migration\Provider;

use Hector\Migration\Exception\MigrationException;
use Hector\Migration\MigrationInterface;

class DirectoryProvider extends AbstractDirectoryProvider
{
    /**
     * @inheritDoc
     */
    protected function resolveFile(string $file): ?MigrationInterface
    {
        $result = require $file;

        // File returned an instance directly
        if ($result instanceof MigrationInterface) {
            return $result;
        }

        // File returned a FQCN string
        if (is_string($result) && class_exists($result)) {
            return $this->instantiate($result);
        }

        throw new MigrationException(
            sprintf(
                'Migration file "%s" must return an instance of %s or a class name string',
                $file,
                MigrationInterface::class,
            )
        );
    }
}
