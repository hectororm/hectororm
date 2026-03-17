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

use Hector\Migration\MigrationInterface;
use Psr\Container\ContainerInterface;

class Psr4Provider extends AbstractDirectoryProvider
{
    /**
     * Psr4Provider constructor.
     *
     * @param string $namespace Base namespace for migration classes (e.g. 'App\\Migration')
     * @param string $directory Path to the migrations directory
     * @param string $pattern Glob pattern for matching files (e.g. '*.php')
     * @param int $depth Max directory depth (-1 = unlimited, 0 = flat, N = N levels)
     * @param ContainerInterface|null $container Optional PSR-11 container for migration instantiation
     */
    public function __construct(
        private string $namespace,
        string $directory,
        string $pattern = '*.php',
        int $depth = -1,
        ?ContainerInterface $container = null,
    ) {
        parent::__construct($directory, $pattern, $depth, $container);
    }

    /**
     * @inheritDoc
     */
    protected function resolveId(string $file, MigrationInterface $migration): string
    {
        return $migration::class;
    }

    /**
     * @inheritDoc
     */
    protected function resolveFile(string $file): ?MigrationInterface
    {
        $fqcn = $this->resolveClassName($file);

        if (null === $fqcn || false === class_exists($fqcn)) {
            return null;
        }

        if (false === is_subclass_of($fqcn, MigrationInterface::class)) {
            return null;
        }

        return $this->instantiate($fqcn);
    }

    /**
     * Resolve the FQCN of a class from its file path using PSR-4 conventions.
     *
     * @param string $file Absolute file path
     *
     * @return string|null FQCN or null if the file is outside the base directory
     */
    private function resolveClassName(string $file): ?string
    {
        $directory = rtrim($this->getDirectory(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        $namespace = rtrim($this->namespace, '\\') . '\\';

        $relativePath = substr($file, strlen($directory));

        if ('' === $relativePath) {
            return null;
        }

        // Remove extension
        $dotPos = strrpos($relativePath, '.');

        if (false !== $dotPos) {
            $relativePath = substr($relativePath, 0, $dotPos);
        }

        // Convert directory separators to namespace separators
        $classPath = str_replace(DIRECTORY_SEPARATOR, '\\', $relativePath);

        return $namespace . $classPath;
    }
}
