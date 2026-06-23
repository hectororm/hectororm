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

use ArrayIterator;
use FilesystemIterator;
use Hector\Migration\Exception\MigrationException;
use Hector\Migration\MigrationInterface;
use Psr\Container\ContainerInterface;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use SplFileInfo;

abstract class AbstractDirectoryProvider implements MigrationProviderInterface
{
    /** @var array<string, MigrationInterface>|null */
    private ?array $migrations = null;

    /**
     * AbstractDirectoryProvider constructor.
     *
     * @param string $directory Path to the migrations directory
     * @param string $pattern Glob pattern for matching files (e.g. '*.php')
     * @param int $depth Max directory depth (-1 = unlimited, 0 = flat, N = N levels)
     * @param ContainerInterface|null $container Optional PSR-11 container for migration instantiation
     */
    public function __construct(
        private string $directory,
        private string $pattern = '*.php',
        private int $depth = 0,
        private ?ContainerInterface $container = null,
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getArrayCopy(): array
    {
        return $this->load();
    }

    /**
     * @inheritDoc
     *
     * @return ArrayIterator<string, MigrationInterface>
     */
    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->load());
    }

    /**
     * @inheritDoc
     */
    public function count(): int
    {
        return count($this->load());
    }

    /**
     * Get the base directory.
     *
     * @return string
     */
    protected function getDirectory(): string
    {
        return $this->directory;
    }

    /**
     * Scan the directory for files matching the pattern, respecting depth.
     *
     * @return list<string> Absolute paths, sorted by file name (then full path as tiebreaker)
     * @throws MigrationException
     */
    protected function scanFiles(): array
    {
        if (false === is_dir($this->directory)) {
            throw new MigrationException(
                sprintf('Migration directory does not exist: %s', $this->directory)
            );
        }

        $iterator = new RecursiveDirectoryIterator(
            $this->directory,
            FilesystemIterator::SKIP_DOTS,
        );

        $recursive = new RecursiveIteratorIterator($iterator);
        $recursive->setMaxDepth($this->depth);

        $baseRealPath = realpath($this->directory);

        $files = [];

        /** @var SplFileInfo $file */
        foreach ($recursive as $file) {
            if (false === fnmatch($this->pattern, $file->getFilename())) {
                continue;
            }

            // Ensure the file is physically within the base directory
            $fileRealPath = $file->getRealPath();

            if (false === $fileRealPath || false === str_starts_with($fileRealPath, $baseRealPath . DIRECTORY_SEPARATOR)) {
                continue;
            }

            $files[] = $fileRealPath;
        }

        // Sort by the provider-defined sort key, falling back to the full path as a stable
        // tiebreaker. The default key is the file name (not the absolute path): in a recursive
        // scan, sorting full paths interleaves directories (e.g. "20260101_First.php" would
        // sort before "sub/20250101_Earlier.php" because '2' < 's'), breaking the chronological
        // order that timestamp-prefixed migration names are meant to convey.
        usort(
            $files,
            fn(string $a, string $b): int => strcmp($this->sortKey($a), $this->sortKey($b)) ?: strcmp($a, $b),
        );

        return $files;
    }

    /**
     * The key used to order files. Defaults to the file name so timestamp-prefixed migrations
     * are ordered chronologically regardless of their subdirectory.
     *
     * @param string $file Absolute file path
     *
     * @return string
     */
    protected function sortKey(string $file): string
    {
        return basename($file);
    }

    /**
     * Instantiate a migration class via container or direct construction.
     *
     * @param string $class FQCN of the migration class
     *
     * @return MigrationInterface
     * @throws MigrationException
     */
    protected function instantiate(string $class): MigrationInterface
    {
        // Guard direct construction: an abstract class (or interface) is a subclass of
        // MigrationInterface but cannot be instantiated; `new $class()` would otherwise raise
        // a raw Error. A container is trusted to build the instance itself.
        if (
            (null === $this->container || false === $this->container->has($class))
            && false === (new ReflectionClass($class))->isInstantiable()
        ) {
            throw new MigrationException(
                sprintf('Migration class "%s" is not instantiable', $class)
            );
        }

        $migration = match (true) {
            null !== $this->container && true === $this->container->has($class) => $this->container->get($class),
            default => new $class(),
        };

        if (false === $migration instanceof MigrationInterface) {
            throw new MigrationException(
                sprintf('Class "%s" must implement %s', $class, MigrationInterface::class)
            );
        }

        return $migration;
    }

    /**
     * Resolve the migration identifier.
     *
     * Default implementation returns the relative file path without extension.
     *
     * @param string $file Absolute file path
     * @param MigrationInterface $migration The migration instance
     *
     * @return string
     */
    protected function resolveId(string $file, MigrationInterface $migration): string
    {
        $directory = rtrim(realpath($this->directory) ?: $this->directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        $relativePath = substr($file, strlen($directory));

        // Remove extension
        $dotPos = strrpos($relativePath, '.');

        if (false !== $dotPos) {
            $relativePath = substr($relativePath, 0, $dotPos);
        }

        // Normalize directory separators for cross-platform consistency
        return str_replace('\\', '/', $relativePath);
    }

    /**
     * Resolve a file into a MigrationInterface instance, or null to skip.
     *
     * @param string $file Absolute path to the PHP file
     *
     * @return MigrationInterface|null The migration instance, or null to skip this file
     * @throws MigrationException
     */
    abstract protected function resolveFile(string $file): ?MigrationInterface;

    /**
     * Load and cache migrations.
     *
     * @return array<string, MigrationInterface>
     */
    private function load(): array
    {
        return $this->migrations ??= $this->loadMigrations();
    }

    /**
     * Load migrations from the directory.
     *
     * @return array<string, MigrationInterface> Keyed by migration identifier, sorted alphabetically
     * @throws MigrationException
     */
    private function loadMigrations(): array
    {
        $files = $this->scanFiles();
        $migrations = [];

        foreach ($files as $file) {
            $migration = $this->resolveFile($file);

            if (null === $migration) {
                continue;
            }

            $id = $this->resolveId($file, $migration);

            if (true === array_key_exists($id, $migrations)) {
                throw new MigrationException(
                    sprintf('Duplicate migration identifier "%s"', $id)
                );
            }

            $migrations[$id] = $migration;
        }

        return $migrations;
    }
}
